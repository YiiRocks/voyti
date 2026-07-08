<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\GitHub;
use YiiRocks\Voyti\Http\ClientInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class GitHubTest extends TestCase
{

    public function testConstructWithoutConfig(): void
    {
        $client = new GitHub();
        self::assertSame('github', $client->getName());
    }
    public function testGetName(): void
    {
        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertSame('github', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertSame('GitHub', $client->getTitle());
    }

    public function testLoadUserAttributesSkipsNonArrayEmails(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($url === 'https://api.github.com/user') {
                    return ['id' => 123, 'login' => 'octocat', 'email' => ''];
                }
                if ($url === 'https://api.github.com/user/emails') {
                    return [
                        'not_an_array',
                        ['email' => 'valid@example.com', 'primary' => true, 'verified' => true],
                    ];
                }
                return [];
            });

        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'loadUserAttributes');
        $result = $ref->invoke($client, ['access_token' => 'token'], $httpClient);

        self::assertSame('valid@example.com', $result['email']);
    }

    public function testLoadUserAttributesWithBreak(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($url === 'https://api.github.com/user') {
                    return ['id' => 123, 'login' => 'octocat'];
                }
                if ($url === 'https://api.github.com/user/emails') {
                    return [
                        ['email' => 'first@example.com', 'primary' => true, 'verified' => true],
                        ['email' => 'second@example.com', 'primary' => true, 'verified' => true],
                    ];
                }
                return [];
            });

        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'loadUserAttributes');
        $result = $ref->invoke($client, ['access_token' => 'token'], $httpClient);

        self::assertSame('first@example.com', $result['email']);
    }

    public function testLoadUserAttributesWithEmailAlreadyPresent(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('send')
            ->with(
                'GET',
                'https://api.github.com/user',
                self::anything(),
                self::anything(),
                self::anything(),
            )
            ->willReturn(['email' => 'user@github.com', 'id' => 123]);

        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'loadUserAttributes');
        $result = $ref->invoke($client, ['access_token' => 'token'], $httpClient);

        self::assertSame('user@github.com', $result['email']);
        self::assertSame(123, $result['id']);
    }

    public function testLoadUserAttributesWithEmptyEmailCandidate(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($url === 'https://api.github.com/user') {
                    return ['id' => 123, 'login' => 'octocat'];
                }
                if ($url === 'https://api.github.com/user/emails') {
                    return [
                        ['email' => '', 'primary' => true, 'verified' => true],
                        ['email' => 'backup@example.com', 'primary' => false, 'verified' => true],
                    ];
                }
                return [];
            });

        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'loadUserAttributes');
        $result = $ref->invoke($client, ['access_token' => 'token'], $httpClient);

        self::assertSame('backup@example.com', $result['email']);
    }

    public function testLoadUserAttributesWithEmptyEmailPicksVerified(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($url === 'https://api.github.com/user') {
                    return ['id' => 123, 'login' => 'octocat', 'email' => ''];
                }
                if ($url === 'https://api.github.com/user/emails') {
                    return [
                        ['email' => 'unverified@example.com', 'primary' => false, 'verified' => false],
                        ['email' => 'verified@example.com', 'primary' => false, 'verified' => true],
                    ];
                }
                return [];
            });

        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'loadUserAttributes');
        $result = $ref->invoke($client, ['access_token' => 'token'], $httpClient);

        self::assertSame('verified@example.com', $result['email']);
    }

    public function testLoadUserAttributesWithoutEmailFetchesEmails(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($url === 'https://api.github.com/user') {
                    return ['id' => 123, 'login' => 'octocat'];
                }
                if ($url === 'https://api.github.com/user/emails') {
                    return [
                        ['email' => 'noreply@github.com', 'primary' => false, 'verified' => false],
                        ['email' => 'primary@github.com', 'primary' => true, 'verified' => true],
                    ];
                }
                return [];
            });

        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'loadUserAttributes');
        $result = $ref->invoke($client, ['access_token' => 'token'], $httpClient);

        self::assertSame('primary@github.com', $result['email']);
    }

    public function testLoadUserAttributesWithPrimaryAsString(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($url === 'https://api.github.com/user') {
                    return ['id' => 123, 'login' => 'octocat'];
                }
                if ($url === 'https://api.github.com/user/emails') {
                    return [
                        ['email' => 'string_primary@example.com', 'primary' => 'yes'],
                    ];
                }
                return [];
            });

        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'loadUserAttributes');
        $result = $ref->invoke($client, ['access_token' => 'token'], $httpClient);

        self::assertSame('string_primary@example.com', $result['email']);
    }

    public function testLoadUserAttributesWithPrimaryNotSet(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($url === 'https://api.github.com/user') {
                    return ['id' => 123, 'login' => 'octocat'];
                }
                if ($url === 'https://api.github.com/user/emails') {
                    return [
                        ['email' => 'no_primary@example.com'],
                        ['email' => 'fallback@example.com', 'primary' => true, 'verified' => true],
                    ];
                }
                return [];
            });

        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'loadUserAttributes');
        $result = $ref->invoke($client, ['access_token' => 'token'], $httpClient);

        self::assertSame('fallback@example.com', $result['email']);
    }

    public function testLoadUserAttributesWithVerifiedAsString(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($url === 'https://api.github.com/user') {
                    return ['id' => 123, 'login' => 'octocat'];
                }
                if ($url === 'https://api.github.com/user/emails') {
                    return [
                        ['email' => 'string_verified@example.com', 'verified' => 'yes'],
                    ];
                }
                return [];
            });

        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'loadUserAttributes');
        $result = $ref->invoke($client, ['access_token' => 'token'], $httpClient);

        self::assertSame('string_verified@example.com', $result['email']);
    }
}
