<?php

namespace SWamp\Client\Roles;

use SWamp\Client\Promise\Deferred;
use SWamp\Client\Session;
use Thruway\Common\Utils;
use Thruway\Message\ErrorMessage;
use Thruway\Message\EventMessage;
use Thruway\Message\Message;
use Thruway\Message\SubscribedMessage;
use Thruway\Message\SubscribeMessage;
use Thruway\Message\UnsubscribedMessage;

class Subscriber extends AbstractRole
{
    private array $subscriptions;

    public function __construct()
    {

        $this->subscriptions = [];
    }

    public function getFeatures(): \stdClass
    {
        $features = new \stdClass();
        $features->publisher_identification = true;
        $features->pattern_based_subscription = true;
        $features->subscription_revocation = true;
        // $features->publication_trustlevels = true;
        // $features->subscriber_metaevents = true;
        // $features->event_history = true;

        return $features;
    }

    public function onMessage(Session $session, Message $msg): void
    {
        if ($msg instanceof SubscribedMessage):
            $this->processSubscribed($session, $msg);
        elseif ($msg instanceof UnsubscribedMessage):
            $this->processUnsubscribed($session, $msg);
        elseif ($msg instanceof EventMessage):
            $this->processEvent($session, $msg);
        elseif ($msg instanceof ErrorMessage):
            $this->processError($session, $msg);
        else:
            $session->sendMessage(ErrorMessage::createErrorMessageFromMessage($msg));
        endif;
    }

    protected function processError(Session $session, ErrorMessage $msg)
    {
        switch ($msg->getErrorMsgCode()) {
            case Message::MSG_SUBSCRIBE:
                $this->processSubscribeError($session, $msg);
                break;
            case Message::MSG_UNSUBSCRIBE:
                // TODO
                break;
            default:
                // Logger::critical($this, 'Unhandled error');
        }
    }

    protected function processSubscribeError(Session $session, ErrorMessage $msg)
    {
        foreach ($this->subscriptions as $key => $subscription) {
            if ($subscription['request_id'] === $msg->getErrorRequestId()) {
                // reject the promise
                $this->subscriptions[$key]['deferred']->reject($msg);

                unset($this->subscriptions[$key]);
                break;
            }
        }
    }

    protected function processSubscribed(Session $session, SubscribedMessage $msg)
    {
        foreach ($this->subscriptions as $key => $subscription) {
            if ($subscription['request_id'] === $msg->getRequestId()) {
                $this->subscriptions[$key]['subscription_id'] = $msg->getSubscriptionId();
                $this->subscriptions[$key]['deferred']->resolve($msg);
                break;
            }
        }
    }

    protected function processUnsubscribed(Session $session, UnsubscribedMessage $msg)
    {
        foreach ($this->subscriptions as $key => $subscription) {
            if (isset($subscription['unsubscribed_request_id']) && $subscription['unsubscribed_request_id'] === $msg->getRequestId()) {
                /* @var $deferred Deferred */
                $deferred = $subscription['unsubscribed_deferred'];
                $deferred->resolve();

                unset($this->subscriptions[$key]);
                return;
            }
        }
//        $this->logger->error("---Got an Unsubscribed Message, but couldn't find corresponding request.\n");
    }

    protected function processEvent(Session $session, EventMessage $msg)
    {
        foreach ($this->subscriptions as $key => $subscription) {
            usleep(1);
            if ($subscription['subscription_id'] === $msg->getSubscriptionId()) {
                call_user_func(
                    $subscription['callback'],
                    $msg->getArguments(),
                    $msg->getArgumentsKw(),
                    $msg->getDetails()
                );
            }
        }
    }

    public function handlesMessage(Message $msg): bool
    {
        $handledMsgCodes = [
            Message::MSG_SUBSCRIBED,
            Message::MSG_UNSUBSCRIBED,
            Message::MSG_EVENT,
            Message::MSG_SUBSCRIBE, // for error handling
            Message::MSG_UNSUBSCRIBE // for error handling
        ];

        $codeToCheck = $msg->getMsgCode();

        if ($msg instanceof ErrorMessage) {
            $codeToCheck = $msg->getErrorMsgCode();
        }

        return in_array($codeToCheck, $handledMsgCodes, true);
    }


    public function subscribe(Session $session, $topicName, callable $callback, array|object $options = [])
    {
        $requestId = Utils::getUniqueId();
        $options   = (object) $options;
        $deferred  = new Deferred();

        $subscription = [
            'topic_name' => $topicName,
            'callback'   => $callback,
            'request_id' => $requestId,
            'options'    => $options,
            'deferred'   => $deferred
        ];

        $this->subscriptions[] = $subscription;

        $subscribeMsg = new SubscribeMessage($requestId, $options, $topicName);
        $session->sendMessage($subscribeMsg);

        return $deferred->promise();
    }
}
