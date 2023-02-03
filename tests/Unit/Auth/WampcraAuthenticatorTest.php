<?php

namespace SWamp\Client\Tests\Unit\Auth;

use SWamp\Client\Auth\WampcraAuthenticator;
use SWamp\Client\Tests\Unit\TestCase;
use Thruway\Message\AuthenticateMessage;
use Thruway\Message\ChallengeMessage;

class WampcraAuthenticatorTest extends TestCase
{
    protected string $key = '';

    public function setUp(): void
    {
        $this->key = 'hTSvg6E7sm44THBUhjZXu6ZVWz4scQsA';
    }

    public function tearDown(): void
    {
        $this->key = '';
    }

    public function testSign()
    {
        $hash = WampcraAuthenticator::sign('secret', $this->key);
        $this->assertSame('6WXz+wmJ9ByOLiYt6ojE0DB1HRKelV0A8znWkz/tLqg=', $hash);
    }

    public function testDeriveKey()
    {
        $key = WampcraAuthenticator::deriveKey('secret', $this->key);
        $this->assertSame('h9KDR4Dx95yElDSpYjHmYpx0XG22liPGrs6LXWkgKsw=', $key);
    }

    public function testGetAuthMethods()
    {
        $authenticator = new WampcraAuthenticator('123abc', $this->key);
        $methods = $authenticator->getAuthMethods();

        $this->assertIsArray($methods);
        $this->assertContains('wampcra', $methods);
    }

    public function testAuthId()
    {
        $authenticator = new WampcraAuthenticator('123abc', $this->key);
        $this->assertSame('123abc', $authenticator->getAuthId());

        $authenticator->setAuthId('1234abcd');
        $this->assertSame('1234abcd', $authenticator->getAuthId());
    }

    /**
     * @dataProvider getAuthenticateFromChallengeFalseProvider
     */
    public function testGetAuthenticateFromChallengeFalse($methods, $details)
    {
        $authenticator = new WampcraAuthenticator('123abc', $this->key);
        $message = new ChallengeMessage($methods, $details);
        $result = $authenticator->getAuthenticateFromChallenge($message);
        $this->assertFalse($result);
    }

    public static function getAuthenticateFromChallengeFalseProvider(): \Generator
    {
        yield ['ticket', []];
        yield ['wampcra', null];
        yield ['wampcra', []];
    }

    public function testGetAuthenticateFromChallenge()
    {
        $authenticator = new WampcraAuthenticator('123abc', $this->key);
        $message = new ChallengeMessage('wampcra', [
            'challenge' => 'hTSvg6E7sm44THBUhjZXu6ZVWz4scQsA',
            'salt' => 'V74xBYVDtHAc3C9uK8SuaBvsQMkKAhfu',
            'keylen' => 12,
            'iterations' => 1020,
        ]);
        $result = $authenticator->getAuthenticateFromChallenge($message);

        $this->assertInstanceOf(AuthenticateMessage::class, $result);
    }
}
