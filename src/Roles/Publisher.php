<?php

namespace SWamp\Client\Roles;

use SWamp\Client\Promise\Deferred;
use SWamp\Client\Promise\PromiseInterface;
use SWamp\Client\Session;
use Thruway\Common\Utils;
use Thruway\Message\ErrorMessage;
use Thruway\Message\Message;
use Thruway\Message\PublishedMessage;
use Thruway\Message\PublishMessage;

class Publisher extends AbstractRole
{
    private array $publishRequests;

    public function __construct()
    {
        $this->publishRequests = [];
    }

    public function getFeatures(): \stdClass
    {
        $features = new \stdClass();

        $features->publisher_identification = true;
        $features->subscriber_blackwhite_listing = true;
        $features->publisher_exclusion = true;

        return $features;
    }

    public function onMessage(Session $session, Message $msg): void
    {
        if ($msg instanceof PublishedMessage):
            $this->processPublished($msg);
        elseif ($msg instanceof ErrorMessage):
            $this->processError($msg);
        else:
            $session->sendMessage(ErrorMessage::createErrorMessageFromMessage($msg));
        endif;
    }

    protected function processPublished(PublishedMessage $msg)
    {

        if (isset($this->publishRequests[$msg->getRequestId()])) {
            /** @var Deferred $futureResult */
            $futureResult = $this->publishRequests[$msg->getRequestId()]['future_result'];
            $futureResult->resolve($msg->getPublicationId());
            unset($this->publishRequests[$msg->getRequestId()]);
        }
    }

    protected function processError(ErrorMessage $msg)
    {
        if (isset($this->publishRequests[$msg->getRequestId()])) {
            /* @var $futureResult Deferred */
            $futureResult = $this->publishRequests[$msg->getRequestId()]['future_result'];
            $futureResult->reject($msg);
            unset($this->publishRequests[$msg->getRequestId()]);
        }
    }

    public function handlesMessage(Message $msg): bool
    {
        $handledMsgCodes = [
            Message::MSG_PUBLISHED,
        ];

        if (in_array($msg->getMsgCode(), $handledMsgCodes, true)) {
            return true;
        } elseif ($msg instanceof ErrorMessage && $msg->getErrorMsgCode() === Message::MSG_PUBLISH) {
            return true;
        } else {
            return false;
        }
    }

    public function publish(Session $session, string $topicName, array $arguments = [], array|object $argumentsKw = [], array|object $options = []): ?PromiseInterface
    {
        $options = (object) $options;

        $requestId = Utils::getUniqueId();

        $futureResult = null;
        if (isset($options->acknowledge) && $options->acknowledge === true) {
            $futureResult = new Deferred();
            $this->publishRequests[$requestId] = ['future_result' => $futureResult];
        }

        $publishMsg = new PublishMessage($requestId, $options, $topicName, $arguments, $argumentsKw);

        $session->sendMessage($publishMsg);

        return $futureResult?->promise();
    }
}
