<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\GitHub;
use YiiRocks\Voyti\Http\ClientInterface;

final class GitHubTest extends TestCase
{

    public function testFetchUserAttributesFallsBackToVerifiedEmailEndpoint(): void
    {
        $httpClient = new RecordingHttpClient([
            'POST https://github.com/login/oauth/access_token' => ['access_token' => 'token'],
            'GET https://api.github.com/user' => [
                'id' => '123',
                'login' => 'octocat',
                'name' => 'Octo Cat',
            ],
            'GET https://api.github.com/user/emails' => [
                ['email' => '', 'verified' => true],
                ['email' => 'verified@example.com', 'verified' => true],
                ['email' => 'secondary@example.com', 'primary' => true],
            ],
        ]);

        $attributes = $this->client()->fetchUserAttributes('code', 'https://example.test/callback', $httpClient);

        self::assertSame('verified@example.com', $attributes['email']);
        self::assertSame(
            [
                'POST https://github.com/login/oauth/access_token',
                'GET https://api.github.com/user',
                'GET https://api.github.com/user/emails',
            ],
            $httpClient->requests,
        );
    }
    public function testFetchUserAttributesKeepsExistingEmailWithoutFallbackLookup(): void
    {
        $httpClient = new RecordingHttpClient([
            'POST https://github.com/login/oauth/access_token' => ['access_token' => 'token'],
            'GET https://api.github.com/user' => [
                'id' => '123',
                'email' => 'profile@example.com',
                'login' => 'octocat',
                'name' => 'Octo Cat',
            ],
            'GET https://api.github.com/user/emails' => [
                ['email' => 'fallback@example.com', 'verified' => true],
            ],
        ]);

        $attributes = $this->client()->fetchUserAttributes('code', 'https://example.test/callback', $httpClient);

        self::assertSame('123', $attributes['id']);
        self::assertSame('profile@example.com', $attributes['email']);
        self::assertSame('octocat', $attributes['username']);
        self::assertSame('Octo Cat', $attributes['name']);
        self::assertSame(
            [
                'POST https://github.com/login/oauth/access_token',
                'GET https://api.github.com/user',
            ],
            $httpClient->requests,
        );
    }

    public function testFetchUserAttributesSkipsUnflaggedFallbackEmails(): void
    {
        $httpClient = new RecordingHttpClient([
            'POST https://github.com/login/oauth/access_token' => ['access_token' => 'token'],
            'GET https://api.github.com/user' => [
                'id' => '123',
                'login' => 'octocat',
                'name' => 'Octo Cat',
            ],
            'GET https://api.github.com/user/emails' => [
                ['email' => 'ignored@example.com'],
                ['email' => 'chosen@example.com', 'primary' => true],
            ],
        ]);

        $attributes = $this->client()->fetchUserAttributes('code', 'https://example.test/callback', $httpClient);

        self::assertSame('chosen@example.com', $attributes['email']);
    }

    private function client(): GitHub
    {
        return new GitHub([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
        ]);
    }
}

final class RecordingHttpClient implements ClientInterface
{
    /** @var list<string> */
    public array $requests = [];

    /**
     * @param array<string, array<string, mixed>> $responses
     */
    public function __construct(
        private readonly array $responses,
    ) {
    }

    #[\Override]
    public function send(
        string $method,
        string $url,
        array $headers = [],
        array $query = [],
        array $body = [],
    ): array {
        $key = strtoupper($method) . ' ' . $url;
        $this->requests[] = $key;

        return $this->responses[$key] ?? [];
    }
}
