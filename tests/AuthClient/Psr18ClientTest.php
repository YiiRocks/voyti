<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use YiiRocks\Voyti\Http\Psr18Client;

final class Psr18ClientTest extends TestCase
{
    public function testSendBuildsRequestAndDecodesJsonResponse(): void
    {
        $psr17Factory = new Psr17Factory();
        $captured = new \stdClass();
        $captured->request = null;

        $httpClient = new class($psr17Factory, $captured) implements ClientInterface {
            public function __construct(
                private readonly Psr17Factory $psr17Factory,
                private readonly \stdClass $captured,
            ) {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->captured->request = $request;

                return $this->psr17Factory
                    ->createResponse()
                    ->withBody($this->psr17Factory->createStream('{"access_token":"token"}'));
            }
        };

        $client = new Psr18Client($httpClient, $psr17Factory, $psr17Factory);

        $response = $client->send(
            'post',
            'https://example.test/token',
            ['Accept' => 'application/json'],
            ['state' => 'abc'],
            ['code' => 'secret'],
        );

        self::assertSame(['access_token' => 'token'], $response);
        self::assertSame('POST', $captured->request->getMethod());
        self::assertSame('https://example.test/token?state=abc', (string) $captured->request->getUri());
        self::assertSame('application/json', $captured->request->getHeaderLine('Accept'));
        self::assertSame('application/x-www-form-urlencoded', $captured->request->getHeaderLine('Content-Type'));
        self::assertSame('code=secret', (string) $captured->request->getBody());
    }

    public function testSendDecodesJsonResponseWithMultipleKeys(): void
    {
        $psr17Factory = new Psr17Factory();

        $httpClient = new class($psr17Factory) implements ClientInterface {
            public function __construct(
                private readonly Psr17Factory $psr17Factory,
            ) {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->psr17Factory
                    ->createResponse()
                    ->withBody($this->psr17Factory->createStream(
                        '{"access_token":"token","token_type":"bearer","expires_in":3600}',
                    ));
            }
        };

        $client = new Psr18Client($httpClient, $psr17Factory, $psr17Factory);

        $response = $client->send('GET', 'https://example.test/token');

        self::assertSame(
            ['access_token' => 'token', 'token_type' => 'bearer', 'expires_in' => 3600],
            $response,
        );
    }

    public function testSendDoesNotAppendQueryStringWhenQueryIsEmpty(): void
    {
        $psr17Factory = new Psr17Factory();
        $captured = new \stdClass();
        $captured->request = null;

        $httpClient = new class($psr17Factory, $captured) implements ClientInterface {
            public function __construct(
                private readonly Psr17Factory $psr17Factory,
                private readonly \stdClass $captured,
            ) {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->captured->request = $request;

                return $this->psr17Factory
                    ->createResponse()
                    ->withBody($this->psr17Factory->createStream('{"access_token":"token"}'));
            }
        };

        $client = new Psr18Client($httpClient, $psr17Factory, $psr17Factory);

        $client->send('GET', 'https://example.test/token?existing=1');

        self::assertSame('https://example.test/token?existing=1', (string) $captured->request->getUri());
    }

    public function testSendPreservesExistingQueryAndDecodesFormEncodedResponse(): void
    {
        $psr17Factory = new Psr17Factory();
        $captured = new \stdClass();
        $captured->request = null;

        $httpClient = new class($psr17Factory, $captured) implements ClientInterface {
            public function __construct(
                private readonly Psr17Factory $psr17Factory,
                private readonly \stdClass $captured,
            ) {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->captured->request = $request;

                return $this->psr17Factory
                    ->createResponse()
                    ->withBody($this->psr17Factory->createStream('access_token=token&refresh_token=refresh'));
            }
        };

        $client = new Psr18Client($httpClient, $psr17Factory, $psr17Factory);

        $response = $client->send(
            'get',
            'https://example.test/token?existing=1',
            query: ['state' => 'abc'],
        );

        self::assertSame(
            ['access_token' => 'token', 'refresh_token' => 'refresh'],
            $response,
        );
        self::assertSame('https://example.test/token?existing=1&state=abc', (string) $captured->request->getUri());
        self::assertSame('', $captured->request->getHeaderLine('Content-Type'));
    }

    public function testSendReturnsFallbackErrorMessageWhenErrorValuesAreNotUsableStrings(): void
    {
        $psr17Factory = new Psr17Factory();

        $httpClient = new class($psr17Factory) implements ClientInterface {
            public function __construct(
                private readonly Psr17Factory $psr17Factory,
            ) {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->psr17Factory
                    ->createResponse(500)
                    ->withBody($this->psr17Factory->createStream('{"error":123,"message":"","error_summary":false}'));
            }
        };

        $client = new Psr18Client($httpClient, $psr17Factory, $psr17Factory);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAuth provider request failed with status 500.');

        $client->send('GET', 'https://example.test/token');
    }

    public function testSendThrowsForNestedNonStringErrorMessageInsteadOfUsingIt(): void
    {
        $psr17Factory = new Psr17Factory();

        $httpClient = new class($psr17Factory) implements ClientInterface {
            public function __construct(
                private readonly Psr17Factory $psr17Factory,
            ) {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->psr17Factory
                    ->createResponse(401)
                    ->withBody($this->psr17Factory->createStream('{"error":{"message":5}}'));
            }
        };

        $client = new Psr18Client($httpClient, $psr17Factory, $psr17Factory);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAuth provider request failed with status 401.');

        $client->send('POST', 'https://example.test/token');
    }

    public function testSendThrowsOauthErrorMessageForFailingResponses(): void
    {
        $psr17Factory = new Psr17Factory();

        $httpClient = new class($psr17Factory) implements ClientInterface {
            public function __construct(
                private readonly Psr17Factory $psr17Factory,
            ) {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->psr17Factory
                    ->createResponse(401)
                    ->withBody($this->psr17Factory->createStream('{"error_description":"Invalid code."}'));
            }
        };

        $client = new Psr18Client($httpClient, $psr17Factory, $psr17Factory);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid code.');

        $client->send('POST', 'https://example.test/token');
    }

    public function testSendTreatsStatus400AsFailure(): void
    {
        $psr17Factory = new Psr17Factory();

        $httpClient = new class($psr17Factory) implements ClientInterface {
            public function __construct(
                private readonly Psr17Factory $psr17Factory,
            ) {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->psr17Factory
                    ->createResponse(400)
                    ->withBody($this->psr17Factory->createStream('{"message":"Bad request."}'));
            }
        };

        $client = new Psr18Client($httpClient, $psr17Factory, $psr17Factory);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Bad request.');

        $client->send('GET', 'https://example.test/token');
    }

    public function testSendUsesNestedErrorMessageWhenTopLevelMessageIsMissing(): void
    {
        $psr17Factory = new Psr17Factory();

        $httpClient = new class($psr17Factory) implements ClientInterface {
            public function __construct(
                private readonly Psr17Factory $psr17Factory,
            ) {
            }

            #[\Override]
            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->psr17Factory
                    ->createResponse(401)
                    ->withBody($this->psr17Factory->createStream('{"error":{"message":"Nested error."}}'));
            }
        };

        $client = new Psr18Client($httpClient, $psr17Factory, $psr17Factory);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nested error.');

        $client->send('POST', 'https://example.test/token');
    }
}
