<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\GitHub;
use YiiRocks\Voyti\Http\ClientInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class GitHubTest extends TestCase
{

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function constructionProvider(): iterable
    {
        yield 'without config' => [[]];
        yield 'with config' => [['clientId' => 'id', 'clientSecret' => 'secret']];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<int, mixed>, string}>
     */
    public static function loadUserAttributesEmailFallbackProvider(): iterable
    {
        yield 'breaks on first primary+verified match' => [
            ['id' => 123, 'login' => 'octocat'],
            [
                ['email' => 'first@example.com', 'primary' => true, 'verified' => true],
                ['email' => 'second@example.com', 'primary' => true, 'verified' => true],
            ],
            'first@example.com',
        ];
        yield 'skips non-array email entries' => [
            ['id' => 123, 'login' => 'octocat', 'email' => ''],
            [
                'not_an_array',
                ['email' => 'valid@example.com', 'primary' => true, 'verified' => true],
            ],
            'valid@example.com',
        ];
        yield 'skips empty email candidates' => [
            ['id' => 123, 'login' => 'octocat'],
            [
                ['email' => '', 'primary' => true, 'verified' => true],
                ['email' => 'backup@example.com', 'primary' => false, 'verified' => true],
            ],
            'backup@example.com',
        ];
        yield 'picks verified over unverified when user email empty' => [
            ['id' => 123, 'login' => 'octocat', 'email' => ''],
            [
                ['email' => 'unverified@example.com', 'primary' => false, 'verified' => false],
                ['email' => 'verified@example.com', 'primary' => false, 'verified' => true],
            ],
            'verified@example.com',
        ];
        yield 'fetches emails when user has none' => [
            ['id' => 123, 'login' => 'octocat'],
            [
                ['email' => 'noreply@github.com', 'primary' => false, 'verified' => false],
                ['email' => 'primary@github.com', 'primary' => true, 'verified' => true],
            ],
            'primary@github.com',
        ];
        yield 'primary as non-bool string is truthy' => [
            ['id' => 123, 'login' => 'octocat'],
            [
                ['email' => 'string_primary@example.com', 'primary' => 'yes'],
            ],
            'string_primary@example.com',
        ];
        yield 'primary not set falls back to next candidate' => [
            ['id' => 123, 'login' => 'octocat'],
            [
                ['email' => 'no_primary@example.com'],
                ['email' => 'fallback@example.com', 'primary' => true, 'verified' => true],
            ],
            'fallback@example.com',
        ];
        yield 'verified as non-bool string is truthy' => [
            ['id' => 123, 'login' => 'octocat'],
            [
                ['email' => 'string_verified@example.com', 'verified' => 'yes'],
            ],
            'string_verified@example.com',
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('constructionProvider')]
    public function testGetName(array $config): void
    {
        $client = new GitHub($config);
        self::assertSame('github', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertSame('GitHub', $client->getTitle());
    }

    /**
     * @param array<string, mixed> $userBody
     * @param array<int, mixed> $emailsBody
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('loadUserAttributesEmailFallbackProvider')]
    public function testLoadUserAttributesEmailFallback(array $userBody, array $emailsBody, string $expectedEmail): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url) use ($userBody, $emailsBody): array {
                if ($url === 'https://api.github.com/user') {
                    return $userBody;
                }
                if ($url === 'https://api.github.com/user/emails') {
                    return $emailsBody;
                }
                return [];
            });

        $client = new GitHub(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'loadUserAttributes');
        $result = $ref->invoke($client, ['access_token' => 'token'], $httpClient);

        self::assertSame($expectedEmail, $result['email']);
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
}
