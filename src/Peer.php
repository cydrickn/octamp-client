<?php

namespace Octamp\Client;

use Swoole\Coroutine\Http\Client as SwooleClient;
use Octamp\Client\Roles\AbstractRole;
use Octamp\Client\Roles\Callee;
use Octamp\Client\Roles\Caller;
use Octamp\Client\Roles\Publisher;
use Octamp\Client\Roles\Subscriber;
use Swoole\Coroutine;
use Swoole\WebSocket\Frame;
use Thruway\Message\AbortMessage;
use Thruway\Message\AuthenticateMessage;
use Thruway\Message\ChallengeMessage;
use Thruway\Message\GoodbyeMessage;
use Thruway\Message\HelloMessage;
use Thruway\Message\Message;
use Thruway\Message\WelcomeMessage;
use Thruway\Serializer\JsonSerializer;
use Thruway\Serializer\SerializerInterface;

class Peer
{
    protected const DEFAULT_OPTIONS = [
        'realm' => 'realm1',
        'path' => '/',
        // automatic reconnection
        'max_retries' => 15,
        'initial_retry_delay' => 1.5,
        'max_retry_delay' => 300,
        'retry_delay_growth' => 1.5,
        'retry_delay_jitter' => 0.1,
        // heartbeat
        'autoping_size' => 4,
    ];

    protected ?SwooleClient $client;
    protected Session $session;
    protected EventDispatcher $eventDispatcher;
    protected SerializerInterface $serializer;
    protected mixed $onChallenge = null;

    protected string $realm;
    protected string $path;

    protected array $options = [];

    /**
     * @var AbstractRole[] $roles
     */
    protected array $roles = [];

    public function __construct(protected string $host, protected int $port, protected $option = [])
    {
        $this->realm = $this->option['realm'] ?? 'realm1';
        $this->path = $this->option['path'] ?? '/';
        $this->eventDispatcher = new EventDispatcher();
        $this->serializer = new JsonSerializer();
        $this->client = null;
    }

    public function setClient(SwooleClient $client): void
    {
        $this->client = $client;
    }

    public function open(): void
    {
        if ($this->client === null) {
            $this->client = new SwooleClient($this->host, $this->port); // @codeCoverageIgnore
        }

        $this->client->setHeaders([
            "User-Agent" => 'Chrome/49.0.2587.3',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Sec-WebSocket-Protocol' => 'wamp.2.json, wamp.2.msgpack'
        ]);

        $upgraded = $this->client->upgrade($this->path);
        Coroutine::create(function () use ($upgraded)  {
            if ($upgraded) {
                $this->startSession();
            }
        });

        Coroutine::create(function () {
            while ($this->client->connected) {
                Coroutine::usleep(1);
                $data = $this->client->recv();
                Coroutine::create(function ($data) {
                    $this->onMessage($data);
                }, $data);
            }
        });
    }

    protected function startSession(): void
    {
        if (!$this->hasRole('publisher')) {
            $this->setPublisher(new Publisher());
        }
        if (!$this->hasRole('subscriber')) {
            $this->setSubscriber(new Subscriber());
        }
        if (!$this->hasRole('caller')) {
            $this->setCaller(new Caller());
        }
        if (!$this->hasRole('callee')) {
            $this->setCallee(new Callee());
        }

        $this->session = new Session($this);
        $this->session->setState(Session::STATE_DOWN);
        $details = (object) [
            'roles' => $this->getRoleInfo(),
            'authmethods' => $this->option['authmethods'] ?? [],
            'authid' => $this->option['authid'] ?? 'anonymous',
        ];

        $message = new HelloMessage($this->realm, $details);
        $this->sendMessage($message);
    }

    public function onMessage(Frame|bool $data): void
    {
        if (!$data) {
            return;  // @codeCoverageIgnore
        }

        $message = $this->serializer->deserialize($data->data);
        if ($message instanceof ChallengeMessage) {
            $this->processChallenge($message);
        } elseif ($message instanceof WelcomeMessage) {
            $this->processWelcome($message);
        } elseif ($message instanceof AbortMessage) {
            $this->processAbort($message);
        } elseif ($message instanceof GoodbyeMessage) {
            $this->processGoodbye($message);
        } else {
            $this->processOther($this->session, $message);
        }
    }

    public function onChallenge(callable $callback): void
    {
        $this->onChallenge = $callback;
    }

    public function onOpen(callable $callback): void
    {
        $this->eventDispatcher->on('open', $callback);
    }

    public function onClose($callback): void
    {
        $this->eventDispatcher->on('close', $callback);
    }

    protected function processWelcome(WelcomeMessage $message): void
    {
        $this->session->setSessionId($message->getSessionId());
        $this->session->setState(Session::STATE_UP);
        $this->session->setDetails($message->getDetails());
        $this->eventDispatcher->emit('open', $this->session, $message);
    }

    protected function processChallenge(ChallengeMessage $message): void
    {
        $token = call_user_func($this->onChallenge, $this->session, $message->getAuthMethod(), $message->getDetails());
        $this->sendMessage(new AuthenticateMessage($token));
        $this->session->setState(Session::STATE_CHALLENGE_SENT);
    }

    protected function processAbort(AbortMessage $message): void
    {
        $this->eventDispatcher->emit('error', [$message->getResponseURI(), $message]);
        $this->close();
    }

    protected function processGoodbye(GoodbyeMessage $message): void
    {
        if (!$this->session->isGoodByeSent()) {
            $goodbyeMsg = new GoodbyeMessage(new \stdClass(), "wamp.close.goodbye_and_out");
            $this->sendMessage($goodbyeMsg);
            $this->session->setGoodByeSent(true);
        }
    }

    public function processOther(Session $session, Message $message): void
    {
        foreach ($this->roles as $role) {
            if ($role->handlesMessage($message)) {
                $role->onMessage($session, $message);
                break;
            }
        }
    }

    public function close(): void
    {
        $this->client->close();
    }

    public function sendMessage(Message $message): void
    {
        Coroutine::create(function ($message) {
            $this->client->push(json_encode($message));
        }, $message);
    }

    protected function getRoleInfo(): array
    {
        return array_map(function ($role) {
            return [ 'features' => $role->getFeatures() ];
        }, $this->roles);
    }

    public function setPublisher(Publisher $publisher): void
    {
        $this->roles['publisher'] = $publisher;
    }

    public function getPublisher(): Publisher
    {
        return $this->roles['publisher'];
    }

    public function setSubscriber(Subscriber $subscriber): void
    {
        $this->roles['subscriber'] = $subscriber;
    }

    public function getSubscriber(): Subscriber
    {
        return $this->roles['subscriber'];
    }

    public function setCaller(Caller $caller): void
    {
        $this->roles['caller'] = $caller;
    }

    public function getCaller(): Caller
    {
        return $this->roles['caller'];
    }

    public function setCallee(Callee $callee): void
    {
        $this->roles['callee'] = $callee;
    }

    public function getCallee(): Callee
    {
        return $this->roles['callee'];
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    public function hasRole(string $name): bool
    {
        return isset($this->roles[$name]);
    }

    public function isConnected(): bool
    {
        return $this->client->connected;
    }

    public function isOpen(): bool
    {
        return $this->session->getState() === Session::STATE_UP;
    }
}
