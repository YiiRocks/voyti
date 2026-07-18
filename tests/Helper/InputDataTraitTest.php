<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Helper\InputDataTrait;
use Yiisoft\Session\SessionInterface;

#[AllowMockObjectsWithoutExpectations]
final class InputDataTraitTest extends TestCase
{
    private object $tester;

    protected function setUp(): void
    {
        $this->tester = new class {
            use InputDataTrait;

            public function exposedFormData(array $body, string $formName): array
            {
                return $this->formData($body, $formName);
            }

            public function exposedNullableStringValue(array $data, string $key): ?string
            {
                return $this->nullableStringValue($data, $key);
            }

            public function exposedParsedBody(ServerRequestInterface $request): array
            {
                return $this->parsedBody($request);
            }

            public function exposedQueryParams(ServerRequestInterface $request): array
            {
                return $this->queryParams($request);
            }

            public function exposedRequestAttributeString(ServerRequestInterface $request, string $name, string $default = ''): string
            {
                return $this->requestAttributeString($request, $name, $default);
            }

            public function exposedSessionArray(SessionInterface $session, string $key): array
            {
                return $this->sessionArray($session, $key);
            }

            public function exposedStringValue(array $data, string $key, string $default = ''): string
            {
                return $this->stringValue($data, $key, $default);
            }
        };
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, array<string, mixed>}>
     */
    public static function formDataProvider(): iterable
    {
        yield 'form name maps to non-array' => [['user' => 'string', 'other' => 'value'], 'user', []];
        yield 'form name exists as array' => [
            ['user' => ['name' => 'John', 'email' => 'john@example.com']],
            'user',
            ['name' => 'John', 'email' => 'john@example.com'],
        ];
        yield 'form name missing returns whole body' => [
            ['name' => 'John', 'email' => 'john@example.com'],
            'user',
            ['name' => 'John', 'email' => 'john@example.com'],
        ];
        yield 'empty body' => [[], 'user', []];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, null|string}>
     */
    public static function nullableStringValueProvider(): iterable
    {
        yield 'empty string' => [['key' => ''], 'key', null];
        yield 'missing key' => [[], 'key', null];
        yield 'non-string' => [['key' => 123], 'key', null];
        yield 'string value' => [['key' => 'hello'], 'key', 'hello'];
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function parsedBodyProvider(): iterable
    {
        yield 'array body' => [['field' => 'value'], ['field' => 'value']];
        yield 'null body' => [null, []];
        yield 'object body' => [new \stdClass(), []];
    }

    /**
     * @return iterable<string, array{string, string, mixed, string}>
     */
    public static function requestAttributeStringProvider(): iterable
    {
        yield 'non-string coerces to default' => ['id', '', 42, ''];
        yield 'missing falls back to given default' => ['id', 'fallback', 'fallback', 'fallback'];
        yield 'string value returned' => ['id', '', '123', '123'];
    }

    /**
     * @return iterable<string, array{string, mixed, array<string, mixed>}>
     */
    public static function sessionArrayProvider(): iterable
    {
        yield 'array value' => ['cart', ['item1' => 'book'], ['item1' => 'book']];
        yield 'non-array value' => ['cart', 'string', []];
        yield 'null value' => ['missing', null, []];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string, string}>
     */
    public static function stringValueProvider(): iterable
    {
        yield 'missing key uses default' => [[], 'key', 'default', 'default'];
        yield 'non-string uses default' => [['key' => 42], 'key', '', ''];
        yield 'null uses default' => [['key' => null], 'key', 'fallback', 'fallback'];
        yield 'string value returned' => [['key' => 'hello'], 'key', '', 'hello'];
    }

    #[DataProvider('formDataProvider')]
    public function testFormData(array $body, string $formName, array $expected): void
    {
        self::assertSame($expected, $this->tester->exposedFormData($body, $formName));
    }

    #[DataProvider('nullableStringValueProvider')]
    public function testNullableStringValue(array $data, string $key, ?string $expected): void
    {
        self::assertSame($expected, $this->tester->exposedNullableStringValue($data, $key));
    }

    #[DataProvider('parsedBodyProvider')]
    public function testParsedBody(mixed $parsedBody, array $expected): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getParsedBody')->willReturn($parsedBody);

        self::assertSame($expected, $this->tester->exposedParsedBody($request));
    }

    public function testQueryParamsReturnsParams(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getQueryParams')->willReturn(['page' => '1']);

        self::assertSame(['page' => '1'], $this->tester->exposedQueryParams($request));
    }

    #[DataProvider('requestAttributeStringProvider')]
    public function testRequestAttributeString(string $name, string $default, mixed $attributeValue, string $expected): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getAttribute')->with($name, $default)->willReturn($attributeValue);

        self::assertSame($expected, $this->tester->exposedRequestAttributeString($request, $name, $default));
    }

    #[DataProvider('sessionArrayProvider')]
    public function testSessionArray(string $key, mixed $sessionValue, array $expected): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())->method('get')->with($key)->willReturn($sessionValue);

        self::assertSame($expected, $this->tester->exposedSessionArray($session, $key));
    }

    #[DataProvider('stringValueProvider')]
    public function testStringValue(array $data, string $key, string $default, string $expected): void
    {
        self::assertSame($expected, $this->tester->exposedStringValue($data, $key, $default));
    }
}
