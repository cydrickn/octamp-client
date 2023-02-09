<?php

namespace Octamp\Client\Roles;

use Octamp\Client\Promise\Deferred;
use Octamp\Client\Promise\ProgressablePromiseInterface;
use Octamp\Client\Promise\ProgressPromise;
use Octamp\Client\Result;
use Octamp\Client\Session;
use Thruway\Common\Utils;
use Thruway\Message\CallMessage;
use Thruway\Message\ErrorMessage;
use Thruway\Message\Message;
use Thruway\Message\ResultMessage;

class Caller extends AbstractRole
{
    /**
     * @var array
     */
    private array $callRequests;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->callRequests = [];
    }

    /**
     * Return supported features
     *
     * @return \stdClass
     */
    public function getFeatures(): \stdClass
    {
        $features = new \stdClass();

        $features->caller_identification    = true;
        $features->progressive_call_results = true;
//        $features->call_timeout = true;
//        $features->call_canceling           = true;

        return $features;
    }

    public function onMessage(Session $session, Message $msg): void
    {

        if ($msg instanceof ResultMessage):
            $this->processResult($msg);
        elseif ($msg instanceof ErrorMessage):
            $this->processError($msg);
        else:
            $session->sendMessage(ErrorMessage::createErrorMessageFromMessage($msg));
        endif;
    }

    protected function processResult(ResultMessage $msg): void
    {
        if (isset($this->callRequests[$msg->getRequestId()])) {
            /* @var $futureResult Deferred */
            $futureResult = $this->callRequests[$msg->getRequestId()]['future_result'];
            if (count($msg->getArguments()) > 1 || count((array)$msg->getArgumentsKw()) > 0) {
                $callResult = new Result($msg->getArguments(), $msg->getArgumentsKw());
            } elseif (count($msg->getArguments()) === 1) {
                $callResult = $msg->getArguments()[0];
            }

            $details = $msg->getDetails();
            if (is_object($details) && isset($details->progress) && $details->progress) {
                $futureResult->progress($callResult ?? null);
            } else {
                if (isset($callResult)) {
                    $futureResult->resolve($callResult);
                } else {
                    $futureResult->resolve();
                }

                unset($this->callRequests[$msg->getRequestId()]);
            }
        }
    }

    protected function processError(ErrorMessage $msg)
    {
        switch ($msg->getErrorMsgCode()) {
            case Message::MSG_CALL:
                if (isset($this->callRequests[$msg->getRequestId()])) {
                    /* @var $futureResult Deferred */
                    $futureResult = $this->callRequests[$msg->getRequestId()]['future_result'];
                    $futureResult->reject($msg);
                    unset($this->callRequests[$msg->getRequestId()]);
                }
                break;
        }
    }

    public function handlesMessage(Message $msg): bool
    {
        $handledMsgCodes = [
            Message::MSG_RESULT,
        ];

        if (in_array($msg->getMsgCode(), $handledMsgCodes)) {
            return true;
        } elseif ($msg instanceof ErrorMessage && $msg->getErrorMsgCode() === Message::MSG_CALL) {
            return true;
        } else {
            return false;
        }
    }

    public function call(Session $session, $procedureName, $arguments = null, $argumentsKw = null, $options = null): ProgressablePromiseInterface
    {
        $requestId = Utils::getUniqueId();

        //This promise gets resolved in Caller::processResult
//        $futureResult = new Deferred(function () use ($session, $requestId) {
//            $session->sendMessage(new CancelMessage($requestId, (object)[]));
//        });
        $futureResult = new Deferred();

        $this->callRequests[$requestId] = [
            'procedure_name' => $procedureName,
            'future_result'  => $futureResult
        ];

        if (is_array($options)) {
            $options = (object) $options;
        }

        if (!is_object($options)) {
            if ($options !== null) {
//                Logger::warning($this, "Options don't appear to be the correct type.");
            }
            $options = new \stdClass();
        }

        $callMsg = new CallMessage($requestId, $options, $procedureName, $arguments, $argumentsKw);

        $session->sendMessage($callMsg);

        return $futureResult->promise();
    }
}
