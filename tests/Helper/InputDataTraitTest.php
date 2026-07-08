<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Helper\InputDataTrait;
use Yiisoft\Session\SessionInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
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

    public function testFormDataReturnsEmptyArrayWhenFormNameIsNotArray(): void
    {
        $body = ['user' => 'string', 'other' => 'value'];
        $result = $this->tester->exposedFormData($body, 'user');
        self::assertSame([], $result);
    }

    public function testFormDataReturnsNestedArrayWhenFormNameExists(): void
    {
        $result = $this->tester->exposedFormData(
            ['user' => ['name' => 'John', 'email' => 'john@example.com']],
            'user',
        );
        self::assertSame(['name' => 'John', 'email' => 'john@example.com'], $result);
    }

    public function testFormDataReturnsWholeBodyWhenFormNameMissing(): void
    {
        $body = ['name' => 'John', 'email' => 'john@example.com'];
        $result = $this->tester->exposedFormData($body, 'user');
        self::assertSame($body, $result);
    }

    public function testFormDataWithEmptyBody(): void
    {
        $result = $this->tester->exposedFormData([], 'user');
        self::assertSame([], $result);
    }

    public function testNullableStringValueReturnsNullForEmptyString(): void
    {
        self::assertNull($this->tester->exposedNullableStringValue(['key' => ''], 'key'));
    }

    public function testNullableStringValueReturnsNullForMissingKey(): void
    {
        self::assertNull($this->tester->exposedNullableStringValue([], 'key'));
    }

    public function testNullableStringValueReturnsNullForNonString(): void
    {
        self::assertNull($this->tester->exposedNullableStringValue(['key' => 123], 'key'));
    }

    public function testNullableStringValueReturnsString(): void
    {
        self::assertSame('hello', $this->tester->exposedNullableStringValue(['key' => 'hello'], 'key'));
    }

    public function testParsedBodyReturnsArray(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getParsedBody')->willReturn(['field' => 'value']);

        self::assertSame(['field' => 'value'], $this->tester->exposedParsedBody($request));
    }

    public function testParsedBodyReturnsEmptyArrayWhenNotArray(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getParsedBody')->willReturn(null);

        self::assertSame([], $this->tester->exposedParsedBody($request));
    }

    public function testParsedBodyReturnsEmptyArrayWhenObject(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getParsedBody')->willReturn(new \stdClass());

        self::assertSame([], $this->tester->exposedParsedBody($request));
    }

    public function testQueryParamsReturnsParams(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getQueryParams')->willReturn(['page' => '1']);

        self::assertSame(['page' => '1'], $this->tester->exposedQueryParams($request));
    }

    public function testRequestAttributeStringReturnsDefaultForNonString(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getAttribute')->with('id', '')->willReturn(42);

        self::assertSame('', $this->tester->exposedRequestAttributeString($request, 'id'));
    }

    public function testRequestAttributeStringReturnsDefaultWhenMissing(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getAttribute')->with('id', 'fallback')->willReturn('fallback');

        self::assertSame('fallback', $this->tester->exposedRequestAttributeString($request, 'id', 'fallback'));
    }

    public function testRequestAttributeStringReturnsValue(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getAttribute')->with('id', '')->willReturn('123');

        self::assertSame('123', $this->tester->exposedRequestAttributeString($request, 'id'));
    }

    public function testSessionArrayReturnsArray(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())->method('get')->with('cart')->willReturn(['item1' => 'book']);

        self::assertSame(['item1' => 'book'], $this->tester->exposedSessionArray($session, 'cart'));
    }

    public function testSessionArrayReturnsEmptyArrayForNonArray(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())->method('get')->with('cart')->willReturn('string');

        self::assertSame([], $this->tester->exposedSessionArray($session, 'cart'));
    }

    public function testSessionArrayReturnsEmptyArrayForNull(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())->method('get')->with('missing')->willReturn(null);

        self::assertSame([], $this->tester->exposedSessionArray($session, 'missing'));
    }

    public function testStringValueReturnsDefaultForMissingKey(): void
    {
        self::assertSame('default', $this->tester->exposedStringValue([], 'key', 'default'));
    }

    public function testStringValueReturnsDefaultForNonString(): void
    {
        self::assertSame('', $this->tester->exposedStringValue(['key' => 42], 'key'));
    }

    public function testStringValueReturnsDefaultForNull(): void
    {
        self::assertSame('fallback', $this->tester->exposedStringValue(['key' => null], 'key', 'fallback'));
    }

    public function testStringValueReturnsEmptyStringDefaultByDefault(): void
    {
        self::assertSame('', $this->tester->exposedStringValue(['key' => 42], 'key'));
    }

    public function testStringValueReturnsValue(): void
    {
        self::assertSame('hello', $this->tester->exposedStringValue(['key' => 'hello'], 'key'));
    }
}
