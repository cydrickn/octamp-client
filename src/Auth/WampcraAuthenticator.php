<?php

namespace SWamp\Client\Auth;

use Thruway\Message\ChallengeMessage;
use Thruway\Message\AuthenticateMessage;
use Thruway\Common\Utils;

class WampcraAuthenticator
{
    /**
     * @var string|int
     */
    public string|int $authid;

    /**
     * @var string
     */
    public ?string $derivedKey;

    /**
     * @var string
     */
    public string $key;

    /**
     * Constructor
     *
     * @param string|int $authid
     * @param string $key
     */
    public function __construct(string|int $authid, string $key = null)
    {
        $this->authid     = $authid;
        $this->derivedKey = null;
        $this->key        = $key;
    }

    public static function sign(string $secret, string $challenge): string
    {
        return base64_encode(hash_hmac('sha256', $challenge, $secret, true));
    }

    public static function deriveKey(string $secret, string $salt, int $iterations = 1000, int $keyLen = 32): string
    {
        return Utils::getDerivedKey($secret, $salt, $iterations, $keyLen);
    }

    /**
     * Get Authenticate message from challenge message
     *
     * @param \Thruway\Message\ChallengeMessage $msg
     * @return \Thruway\Message\AuthenticateMessage|boolean
     */
    public function getAuthenticateFromChallenge(ChallengeMessage $msg): AuthenticateMessage|bool
    {
        // Logger::info($this, 'Got challenge');
        // Logger::debug($this, 'Challenge Message: ' . json_encode($msg));


        if (!in_array($msg->getAuthMethod(), $this->getAuthMethods(), true)) {
            //throw new \Exception('method isn't in methods');
            return false;
        }

        $details = $msg->getDetails();
        if (isset($details->challenge)) {
            $challenge = $details->challenge;
        } else {
            // Logger::info($this, 'No challenge for wampcra?');
            return false;
        }

        $keyToUse = $this->key;
        if (isset($details->salt)) {
            // we need a salted key
            $salt   = $details->salt;
            $keyLen = 32;
            if (isset($details->keylen)) {
                if (is_numeric($details->keylen)) {
                    $keyLen = $details->keylen;
                }
                // else {
                //    Logger::error($this, 'keylen is not numeric.');
                // }
            }
            $iterations = 1000;
            if (isset($details->iterations)) {
                if (is_numeric($details->iterations)) {
                    $iterations = $details->iterations;
                }
                // else {
                //    Logger::error($this, 'iterations is not numeric.');
                // }
            }

            $keyToUse = Utils::getDerivedKey($this->key, $salt, $iterations, $keyLen);
        }

        $token = base64_encode(hash_hmac('sha256', $challenge, $keyToUse, true));

        $authMessage = new AuthenticateMessage($token);

        // Logger::debug($this, 'returning: ' . json_encode($authMessage));

        return $authMessage;
    }

    /**
     * Get authentication ID
     *
     * @return string
     */
    public function getAuthId()
    {
        return $this->authid;
    }

    /**
     * Set authentication ID
     *
     * @param string $authid
     */
    public function setAuthId($authid)
    {
        $this->authid = $authid;
    }

    /**
     * Get list authenticate methods
     *
     * @return array
     */
    public function getAuthMethods()
    {
        return ['wampcra'];
    }
}
