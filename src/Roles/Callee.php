<?php

namespace Octamp\Client\Roles;

use Octamp\Client\Peer;
use Octamp\Client\Promise\CancellablePromiseInterface;
use Octamp\Client\Promise\Deferred;
use Octamp\Client\Promise\ProgressablePromiseInterface;
use Octamp\Client\Promise\ProgressPromise;
use Octamp\Client\Promise\PromiseInterface;
use Octamp\Client\Result;
use Octamp\Client\Session;
use Thruway\Common\Utils;
use Thruway\Message\ErrorMessage;
use Thruway\Message\InterruptMessage;
use Thruway\Message\InvocationMessage;
use Thruway\Message\Message;
use Thruway\Message\RegisteredMessage;
use Thruway\Message\RegisterMessage;
use Thruway\Message\UnregisteredMessage;
use Thruway\Message\UnregisterMessage;
use Thruway\Message\YieldMessage;
use Thruway\WampErrorException;

class Callee extends AbstractRole
{
    /**
     * @var array
     */
    private array $registrations;

    /**
     * @var array
     */
    private array $invocationCanceller = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->registrations = [];
    }

    /**
     * Return supported features
     *
     * @return \stdClass
     */
    public function getFeatures(): \stdClass
    {
        $features = new \stdClass();

        $features->caller_identification = true;
        // $features->call_trustlevels = true;
        $features->pattern_based_registration = true;
        $features->shared_registration = true;
//        $features->call_timeout = true;
//        $features->call_canceling = true;
        $features->progressive_call_results = true;
        $features->registration_revocation = true;

        return $features;
    }

    public function onMessage(Session $session, Message $msg): void
    {
        if ($msg instanceof RegisteredMessage):
            $this->processRegistered($msg);
        elseif ($msg instanceof UnregisteredMessage):
            $this->processUnregistered($msg);
        elseif ($msg instanceof InvocationMessage):
            $this->processInvocation($session, $msg);
        elseif ($msg instanceof InterruptMessage):
            $this->processInterrupt($session, $msg);
        elseif ($msg instanceof ErrorMessage):
            $this->processError($session, $msg);
        else:
            $session->sendMessage(ErrorMessage::createErrorMessageFromMessage($msg));
        endif;
    }

    protected function processRegistered(RegisteredMessage $msg)
    {
        foreach ($this->registrations as $key => $registration) {
            if ($registration["request_id"] === $msg->getRequestId()) {
//                Logger::info($this, "Setting registration_id for ".$registration['procedure_name']." (".$key.")");
                $this->registrations[$key]['registration_id'] = $msg->getRegistrationId();

                if ($this->registrations[$key]['futureResult'] instanceof Deferred) {
                    $futureResult = $this->registrations[$key]['futureResult'];
                    $futureResult->resolve();
                }

                return;
            }
        }
//        Logger::error($this, "Got a Registered Message, but the request ids don't match");
    }

    protected function processUnregistered(UnregisteredMessage $msg)
    {
        foreach ($this->registrations as $key => $registration) {
            if (isset($registration['unregister_request_id'])) {
                if ($registration["unregister_request_id"] == $msg->getRequestId()) {
                    $deferred = $registration['unregister_deferred'];
                    $deferred->resolve();

                    unset($this->registrations[$key]);

                    return;
                }
            }
        }
//        Logger::error($this, "Got an Unregistered Message, but couldn't find corresponding request");
    }

    private function processExceptionFromRPCCall(Session $session, InvocationMessage $msg, $registration, \Exception $e) {
        if ($e instanceof WampErrorException) {
            $errorMsg = ErrorMessage::createErrorMessageFromMessage($msg);
            $errorMsg->setErrorURI($e->getErrorUri());
            $errorMsg->setArguments($e->getArguments());
            $errorMsg->setArgumentsKw($e->getArgumentsKw());
            $errorMsg->setDetails($e->getDetails());

            $session->sendMessage($errorMsg);
            return;
        }

        $errorMsg = ErrorMessage::createErrorMessageFromMessage($msg);
        $errorMsg->setErrorURI($registration['procedure_name'].'.error');
        $errorMsg->setArguments([$e->getMessage()]);
        $errorMsg->setArgumentsKw($e);

        $session->sendMessage($errorMsg);
    }

    protected function processInvocation(Session $session, InvocationMessage $msg)
    {
        foreach ($this->registrations as $key => $registration) {
            if (!isset($registration['registration_id'])) {
//                Logger::info($this, 'Registration_id not set for '.$registration['procedure_name']);
            } else {
                if ($registration['registration_id'] === $msg->getRegistrationId()) {

                    if ($registration['callback'] === null) {
                        // this is where calls end up if the client has called unregister but
                        // have not yet received confirmation from the router about the
                        // unregistration
                        $session->sendMessage(ErrorMessage::createErrorMessageFromMessage($msg));

                        return;
                    }

                    try {
                        $results = $registration['callback']($msg->getArguments(), $msg->getArgumentsKw(), $msg->getDetails());

                        if ($results instanceof PromiseInterface) {
                            if ($results instanceof CancellablePromiseInterface) {
                                $this->invocationCanceller[$msg->getRequestId()] = function () use ($results) {
                                    $results->cancel();
                                };
                                $results = $results->then(function ($result) use ($msg) {
                                    unset($this->invocationCanceller[$msg->getRequestId()]);
                                    return $result;
                                });
                            }
                            $this->processResultAsPromise($results, $msg, $session, $registration);
                        } else {
                            $this->processResultAsArray($results, $msg, $session);
                        }

                    } catch (\Exception $e) {
                        $this->processExceptionFromRPCCall($session, $msg, $registration, $e);
                    }

                    break;
                }
            }
        }

    }

    private function processInterrupt(Session $session, InterruptMessage $msg)
    {
        if (isset($this->invocationCanceller[$msg->getRequestId()])) {
            $callable = $this->invocationCanceller[$msg->getRequestId()];
            unset($this->invocationCanceller[$msg->getRequestId()]);
            $callable();
        }
    }

    private function processResultAsPromise(PromiseInterface $promise, InvocationMessage $msg, Session $session, $registration)
    {
        $promise->then(
            function ($promiseResults) use ($msg, $session) {
                $options = new \stdClass();
                if ($promiseResults instanceof Result) {
                    $yieldMsg = new YieldMessage($msg->getRequestId(), $options,
                        $promiseResults->getArguments(), $promiseResults->getArgumentsKw());
                } else {
                    $promiseResults = is_array($promiseResults) ? $promiseResults : [$promiseResults];
                    $promiseResults = !$this::is_list($promiseResults) ? [$promiseResults] : $promiseResults;

                    $yieldMsg = new YieldMessage($msg->getRequestId(), $options, $promiseResults);
                }

                $session->sendMessage($yieldMsg);
            },
            function ($e) use ($msg, $session, $registration) {
                if ($e instanceof \Exception) {
                    $this->processExceptionFromRPCCall($session, $msg, $registration, $e);
                    return;
                }

                $errorMsg = ErrorMessage::createErrorMessageFromMessage($msg);
                $errorMsg->setErrorURI($registration['procedure_name'].'.error');

                $session->sendMessage($errorMsg);
            },
            function ($results) use ($msg, $session, $registration) {
                $options           = new \stdClass();
                $options->progress = true;
                if ($results instanceof Result) {
                    $yieldMsg = new YieldMessage($msg->getRequestId(), $options, $results->getArguments(),
                        $results->getArgumentsKw());
                } else {
                    $results = is_array($results) ? $results : [$results];
                    $results = !$this::is_list($results) ? [$results] : $results;

                    $yieldMsg = new YieldMessage($msg->getRequestId(), $options, $results);
                }

                $session->sendMessage($yieldMsg);
            }
        );
    }

    private function processResultAsArray($results, InvocationMessage $msg, Session $session)
    {
        $options = new \stdClass();
        if ($results instanceof Result) {
            $yieldMsg = new YieldMessage($msg->getRequestId(), $options, $results->getArguments(),
                $results->getArgumentsKw());
        } else {
            $results = is_array($results) ? $results : [$results];
            $results = !$this::is_list($results) ? [$results] : $results;

            $yieldMsg = new YieldMessage($msg->getRequestId(), $options, $results);
        }

        $session->sendMessage($yieldMsg);
    }

    public function processError(Session $session, ErrorMessage $msg)
    {
        if ($msg->getErrorMsgCode() === Message::MSG_REGISTER) {
            $this->handleErrorRegister($session, $msg);
        } elseif ($msg->getErrorMsgCode() === Message::MSG_UNREGISTER) {
            $this->handleErrorUnregister($session, $msg);
        } else {
//            Logger::error($this, 'Unhandled error message: '.json_encode($msg));
        }
    }

    public function handleErrorRegister(Session $session, ErrorMessage $msg)
    {
        foreach ($this->registrations as $key => $registration) {
            if ($registration['request_id'] === $msg->getRequestId()) {
                /** @var Deferred $deferred */
                $deferred = $registration['futureResult'];
                $deferred->reject($msg);
                unset($this->registrations[$key]);
                break;
            }
        }
    }

    public function handleErrorUnregister(Session $session, ErrorMessage $msg)
    {
        foreach ($this->registrations as $key => $registration) {
            if (isset($registration['unregister_request_id'])) {
                if ($registration['unregister_request_id'] === $msg->getRequestId()) {
                    /** @var Deferred $deferred */
                    $deferred = $registration['unregister_deferred'];
                    $deferred->reject($msg);

                    // I guess we get rid of the registration now?
                    unset($this->registrations[$key]);
                    break;
                }
            }
        }
    }

    /**
     * Returns true if this role handles this message.
     * Error messages are checked according to the
     * message the error corresponds to.
     *
     * @param \Thruway\Message\Message $msg
     * @return boolean
     */
    public function handlesMessage(Message $msg): bool
    {
        $handledMsgCodes = [
            Message::MSG_REGISTERED,
            Message::MSG_UNREGISTERED,
            Message::MSG_INVOCATION,
            Message::MSG_REGISTER,
            Message::MSG_INTERRUPT
        ];

        $codeToCheck = $msg->getMsgCode();

        if ($msg instanceof ErrorMessage) {
            $codeToCheck = $msg->getErrorMsgCode();
        }

        if (in_array($codeToCheck, $handledMsgCodes, true)) {
            return true;
        } else {
            return false;
        }
    }

    public function register(Session $session, $procedureName, callable $callback, array|object $options = []): ProgressablePromiseInterface
    {
        $futureResult = new Deferred();

        $requestId    = Utils::getUniqueId();
        $options      = (object) $options;
        $registration = [
            'procedure_name' => $procedureName,
            'callback'       => $callback,
            'request_id'     => $requestId,
            'options'        => $options,
            'futureResult'   => $futureResult
        ];

        $this->registrations[] = $registration;

        $registerMsg = new RegisterMessage($requestId, $options, $procedureName);

        $session->sendMessage($registerMsg);

        return $futureResult->promise();
    }

    public function unregister(Session $session, $Uri)
    {
        // TODO: maybe add an option to wait for pending calls to finish

        $registration = null;

        foreach ($this->registrations as $k => $r) {
            if (isset($r['procedure_name'])) {
                if ($r['procedure_name'] === $Uri) {
                    $registration = &$this->registrations[$k];
                    break;
                }
            }
        }

        if ($registration === null) {
//            Logger::warning($this, 'registration not found: '.$Uri);

            return false;
        }

        // we remove the callback from the client here
        // because we don't want the client to respond to any more calls
        $registration['callback'] = null;

        $futureResult = new Deferred();

        if (!isset($registration['registration_id'])) {
            // this would happen if the registration was never acknowledged by the router
            // we should remove the registration and resolve any pending deferreds
//            Logger::error($this, 'Registration ID is not set while attempting to unregister '.$Uri);

            // reject the pending registration
            $registration['futureResult']->reject();

            // TODO: need to figure out what to do in this off chance
            // We should still probably return a promise here that just rejects
            // there is an issue with the pending registration too that
            // the router may have a "REGISTERED" in transit and may still think that is
            // good to go - so maybe still send the unregister?
        }

        $requestId = Utils::getUniqueId();

        // save the request id so we can find this in the registration
        // list to call the deferred and remove it from the list
        $registration['unregister_request_id'] = $requestId;
        $registration['unregister_deferred']   = $futureResult;

        $unregisterMsg = new UnregisterMessage($requestId, $registration['registration_id']);

        $session->sendMessage($unregisterMsg);

        return $futureResult->promise();
    }

    /**
     * This belongs somewhere else I am thinking
     *
     * @param array $array
     * @return boolean
     */
    public static function is_list($array)
    {
        if (!is_array($array)) {
            return false;
        }

        // Keys of the array
        $keys = array_keys($array);

        // If the array keys of the keys match the keys, then the array must
        // not be associative (e.g. the keys array looked like {0:0, 1:1...}).
        return array_keys($keys) === $keys;
    }
}
