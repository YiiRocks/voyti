<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Http;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use YiiRocks\Voyti\Http\Psr18Client;

#[AllowMockObjectsWithoutExpectations]
final class Psr18ClientTest extends TestCase
{
    public function testErrorWithErrorDescription(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('{"error_description":"Invalid grant"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getStatusCode')->willReturn(400);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid grant');

        $client->send('POST', 'https://api.example.com/token');
    }

    public function testErrorWithErrorSummary(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('{"error_summary":"Summary error"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getStatusCode')->willReturn(400);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Summary error');

        $client->send('POST', 'https://api.example.com/token');
    }

    public function testErrorWithMessageKey(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('{"message":"Something went wrong"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getStatusCode')->willReturn(400);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Something went wrong');

        $client->send('POST', 'https://api.example.com/token');
    }

    public function testErrorWithNestedError(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('{"error":{"message":"Nested error"}}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getStatusCode')->willReturn(400);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nested error');

        $client->send('POST', 'https://api.example.com/token');
    }

    public function testErrorWithNestedNonStringMessageUsesDefaultMessage(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('{"error":{"message":123}}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getStatusCode')->willReturn(400);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAuth provider request failed with status 400.');

        $client->send('POST', 'https://api.example.com/token');
    }

    public function testSendDecodesFormUrlEncodedResponse(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('access_token=tok123&token_type=Bearer');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $result = $client->send('POST', 'https://api.example.com/token');

        self::assertSame('tok123', $result['access_token']);
        self::assertSame('Bearer', $result['token_type']);
    }

    public function testSendGetRequest(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('GET', 'https://api.example.com/users')
            ->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->expects(self::once())->method('__toString')->willReturn('{"success":true,"data":"value"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getBody')->willReturn($responseBody);
        $response->expects(self::once())->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->expects(self::once())->method('sendRequest')->with($request)->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $result = $client->send('GET', 'https://api.example.com/users');

        self::assertSame(['success' => true, 'data' => 'value'], $result);
    }

    public function testSendNormalizesHttpMethodToUppercase(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('GET', 'https://api.example.com/resource')
            ->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('{}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $client->send('get', 'https://api.example.com/resource');
    }

    public function testSendPostRequestWithBody(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();
        $request->method('withBody')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('POST', 'https://api.example.com/token')
            ->willReturn($request);

        $stream = $this->createMock(StreamInterface::class);

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->expects(self::once())
            ->method('createStream')
            ->with('client_id=myapp&client_secret=secret')
            ->willReturn($stream);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->expects(self::once())->method('__toString')->willReturn('{"access_token":"tok123"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getBody')->willReturn($responseBody);
        $response->expects(self::once())->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->expects(self::once())->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
        );

        $result = $client->send('POST', 'https://api.example.com/token', [], [], [
            'client_id' => 'myapp',
            'client_secret' => 'secret',
        ]);

        self::assertSame(['access_token' => 'tok123'], $result);
    }

    public function testSendThrowsRuntimeExceptionOnClientException(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $prevException = $this->createMock(ClientExceptionInterface::class);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->method('sendRequest')->willThrowException($prevException);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to contact OAuth provider at 'https://api.example.com/fail'.");

        $client->send('GET', 'https://api.example.com/fail');
    }

    public function testSendWith400StatusCodeThrowsRuntimeException(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('{"error":"bad_request"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getStatusCode')->willReturn(400);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bad_request');

        $client->send('GET', 'https://api.example.com/fail');
    }

    public function testSendWith500StatusCodeUsesDefaultMessage(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('{}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getStatusCode')->willReturn(500);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAuth provider request failed with status 500.');

        $client->send('GET', 'https://api.example.com/error');
    }

    public function testSendWithExistingQueryInUrl(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('GET', 'https://api.example.com/search?existing=1&extra=param')
            ->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('[]');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->expects(self::once())->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $result = $client->send('GET', 'https://api.example.com/search?existing=1', [], ['extra' => 'param']);

        self::assertSame([], $result);
    }

    public function testSendWithHeaders(): void
    {
        $request = $this->createMockRequest();
        $request
            ->expects(self::exactly(2))
            ->method('withHeader')
            ->willReturnCallback(function (string $name, mixed $value) use ($request) {
                return $request;
            });

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('{}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $result = $client->send('GET', 'https://api.example.com/data', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer tok',
        ]);

        self::assertSame([], $result);
    }

    public function testSendWithQueryParameters(): void
    {
        $request = $this->createMockRequest();
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('GET', 'https://api.example.com/search?q=test&page=1')
            ->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('__toString')->willReturn('[]');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($responseBody);
        $response->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(PsrClientInterface::class);
        $httpClient->expects(self::once())->method('sendRequest')->willReturn($response);

        $client = $this->createClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
        );

        $result = $client->send('GET', 'https://api.example.com/search', [], ['q' => 'test', 'page' => '1']);

        self::assertSame([], $result);
    }
    private function createClient(
        ?PsrClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): Psr18Client {
        return new Psr18Client(
            $httpClient ?? $this->createMock(PsrClientInterface::class),
            $requestFactory ?? $this->createMock(RequestFactoryInterface::class),
            $streamFactory ?? $this->createMock(StreamFactoryInterface::class),
        );
    }

    private function createMockRequest(): RequestInterface
    {
        return $this->createMock(RequestInterface::class);
    }
}
