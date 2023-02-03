<?php

namespace SWamp\Client;

use Co\Http\Client as SwooleClient;
use SWamp\Client\Roles\AbstractRole;
use SWamp\Client\Roles\Callee;
use SWamp\Client\Roles\Caller;
use SWamp\Client\Roles\Publisher;
use SWamp\Client\Roles\Subscriber;
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
    protected SwooleClient $client;
    protected Session $session;
    protected EventDispatcher $eventDispatcher;
    protected SerializerInterface $serializer;

    protected string $realm;
    protected string $path;

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
    }

    public function open(): void
    {
        Coroutine\run(function () {
            $this->client = new SwooleClient($this->host, $this->port);

            $this->client->setHeaders([
                "User-Agent" => 'Chrome/49.0.2587.3',
                'Accept' => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Sec-WebSocket-Protocol' => 'wamp.2.json, wamp.2.msgpack'
            ]);

            $upgraded = $this->client->upgrade($this->path);
            Coroutine\go(function () {
                while (true) {
                    $data = $this->client->recv();
                    Coroutine\go(function ($data) {
                        $this->onMessage($data);
                    }, $data);
                }
            });

            Coroutine\go(function () use ($upgraded)  {
                if ($upgraded) {
                    $this->startSession();
                }
            });
        });
    }

    protected function startSession()
    {
        $this->roles['publisher'] = new Publisher();
        $this->roles['subscriber'] = new Subscriber();
        $this->roles['caller'] = new Caller();
        $this->roles['callee'] = new Callee();

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
            return;
        }

        $message = $this->serializer->deserialize($data->data);
        if ($message instanceof ChallengeMessage) {
            $this->processChallenge($message);
        } elseif ($message instanceof WelcomeMessage) {
            $this->processWelcome($message);
        } elseif ($message instanceof AbortMessage) {

        } elseif ($message instanceof GoodbyeMessage) {

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

    protected function processWelcome(WelcomeMessage $message): void
    {
        $this->session->setSessionId($message->getSessionId());
        $this->session->setState(Session::STATE_UP);

        $this->eventDispatcher->emit('open', $this->session);
    }

    protected function processChallenge(ChallengeMessage $message): void
    {
        $token = call_user_func($this->onChallenge, $this->session, $message->getAuthMethod(), $message->getDetails());

        $this->sendMessage(new AuthenticateMessage($token));
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

    public function close(string $reason): void
    {
    }

    public function sendMessage(Message $message)
    {
        $this->client->push(json_encode($message));
    }

    protected function getRoleInfo(): array
    {
        return array_map(function ($role) {
            return [ 'features' => $role->getFeatures() ];
        }, $this->roles);
    }

    public function getPublisher(): Publisher
    {
        return $this->roles['publisher'];
    }

    public function getSubscriber(): Subscriber
    {
        return $this->roles['subscriber'];
    }

    public function getCaller(): Caller
    {
        return $this->roles['caller'];
    }

    public function getCallee(): Callee
    {
        return $this->roles['callee'];
    }
}
