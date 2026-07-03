<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Helper\InputDataTrait;
use Yiisoft\Session\SessionInterface;

final class InputDataTraitTest extends TestCase
{

    public function testFormDataFallsBackToBodyWhenFormNameMissing(): void
    {
        $fixture = new InputDataTraitFixture();

        self::assertSame(['name' => 'alice'], $fixture->callFormData(['name' => 'alice'], 'Form'));
    }
    public function testFormDataIsReachableThroughSubclass(): void
    {
        $fixture = new InputDataTraitSubFixture();

        self::assertSame(['name' => 'alice'], $fixture->callFormData(['Form' => ['name' => 'alice']], 'Form'));
    }

    public function testFormDataReturnsEmptyArrayWhenValueIsNotArray(): void
    {
        $fixture = new InputDataTraitFixture();

        self::assertSame([], $fixture->callFormData(['Form' => 'not-an-array'], 'Form'));
    }

    public function testParsedBodyIsReachableThroughSubclass(): void
    {
        $fixture = new InputDataTraitSubFixture();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['key' => 'value']);

        self::assertSame(['key' => 'value'], $fixture->callParsedBody($request));
    }

    public function testParsedBodyReturnsEmptyArrayWhenNotArray(): void
    {
        $fixture = new InputDataTraitFixture();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(null);

        self::assertSame([], $fixture->callParsedBody($request));
    }

    public function testQueryParamsIsReachableThroughSubclass(): void
    {
        $fixture = new InputDataTraitSubFixture();
        $request = (new ServerRequest('GET', '/'))->withQueryParams(['q' => 'search']);

        self::assertSame(['q' => 'search'], $fixture->callQueryParams($request));
    }

    public function testSessionArrayIsReachableThroughSubclass(): void
    {
        $fixture = new InputDataTraitSubFixture();
        $session = new InputDataTraitFakeSession(['flash' => ['a', 'b']]);

        self::assertSame(['a', 'b'], $fixture->callSessionArray($session, 'flash'));
    }

    public function testSessionArrayReturnsEmptyArrayWhenValueIsNotArray(): void
    {
        $fixture = new InputDataTraitFixture();
        $session = new InputDataTraitFakeSession(['flash' => 'not-an-array']);

        self::assertSame([], $fixture->callSessionArray($session, 'flash'));
    }

    public function testStringValueDoesNotFallBackToDefaultWhenKeyValueIsPresentString(): void
    {
        // Kills the Coalesce mutant ($default ?? $data[$key]) which would return the
        // non-empty default instead of the present key value.
        $fixture = new InputDataTraitFixture();

        self::assertSame('present', $fixture->callStringValue(['name' => 'present'], 'name', 'fallback'));
    }

    public function testStringValueIsReachableThroughSubclass(): void
    {
        $fixture = new InputDataTraitSubFixture();

        self::assertSame('alice', $fixture->callStringValue(['name' => 'alice'], 'name'));
    }

    public function testStringValueUsesDefaultWhenKeyIsMissing(): void
    {
        $fixture = new InputDataTraitFixture();

        self::assertSame('fallback', $fixture->callStringValue(['name' => 'alice'], 'missing', 'fallback'));
    }

    public function testStringValueUsesDefaultWhenPresentValueIsNotString(): void
    {
        $fixture = new InputDataTraitFixture();

        self::assertSame('fallback', $fixture->callStringValue(['name' => 42], 'name', 'fallback'));
    }
}

class InputDataTraitFixture
{
    use InputDataTrait;

    /**
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    public function callFormData(array $body, string $formName): array
    {
        return $this->formData($body, $formName);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function callParsedBody(ServerRequestInterface $request): array
    {
        return $this->parsedBody($request);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function callQueryParams(ServerRequestInterface $request): array
    {
        return $this->queryParams($request);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function callSessionArray(SessionInterface $session, string $key): array
    {
        return $this->sessionArray($session, $key);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function callStringValue(array $data, string $key, string $default = ''): string
    {
        return $this->stringValue($data, $key, $default);
    }
}

final class InputDataTraitSubFixture extends InputDataTraitFixture
{
    /**
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    public function callFormData(array $body, string $formName): array
    {
        // Calls the protected trait method directly from a subclass. If the
        // trait method's visibility is narrowed to private, this call fails
        // with an Error because private methods are not inherited.
        return $this->formData($body, $formName);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function callParsedBody(ServerRequestInterface $request): array
    {
        return $this->parsedBody($request);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function callQueryParams(ServerRequestInterface $request): array
    {
        return $this->queryParams($request);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function callSessionArray(SessionInterface $session, string $key): array
    {
        return $this->sessionArray($session, $key);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function callStringValue(array $data, string $key, string $default = ''): string
    {
        return $this->stringValue($data, $key, $default);
    }
}

final class InputDataTraitFakeSession implements SessionInterface
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(private array $data = [])
    {
    }

    #[\Override]
    public function all(): array
    {
        return $this->data;
    }

    #[\Override]
    public function clear(): void
    {
        $this->data = [];
    }

    #[\Override]
    public function close(): void
    {
    }

    #[\Override]
    public function destroy(): void
    {
        $this->data = [];
    }

    #[\Override]
    public function discard(): void
    {
    }

    #[\Override]
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    #[\Override]
    public function getCookieParameters(): array
    {
        return [];
    }

    #[\Override]
    public function getId(): ?string
    {
        return 'fake-session-id';
    }

    #[\Override]
    public function getName(): string
    {
        return 'fake-session';
    }

    #[\Override]
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    #[\Override]
    public function isActive(): bool
    {
        return true;
    }

    #[\Override]
    public function open(): void
    {
    }

    #[\Override]
    public function pull(string $key, $default = null)
    {
        $value = $this->get($key, $default);
        unset($this->data[$key]);

        return $value;
    }

    #[\Override]
    public function regenerateId(): void
    {
    }

    #[\Override]
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    #[\Override]
    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    #[\Override]
    public function setId(string $sessionId): void
    {
    }
}
