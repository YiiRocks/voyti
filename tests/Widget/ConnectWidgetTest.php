<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Widget;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\AuthClient\GitHub;
use YiiRocks\Voyti\Widget\ConnectWidget;
use Yiisoft\Router\UrlGeneratorInterface;

final class ConnectWidgetTest extends TestCase
{
    public function testRenderUsesProviderRouteParameterAndClientTitle(): void
    {
        $widget = new ConnectWidget(
            new FakeUrlGenerator(),
            new AuthClientRegistry(new GitHub()),
        );

        $html = $widget->render();

        self::assertStringContainsString('/voyti/auth/github', $html);
        self::assertStringContainsString('GitHub', $html);
        self::assertStringNotContainsString('authclient', $html);
    }
}

final class FakeUrlGenerator implements UrlGeneratorInterface
{
    #[\Override]
    public function generate(string $name, array $arguments = [], array $queryParameters = [], ?string $hash = null): string
    {
        return $this->format($name, $arguments, $queryParameters, $hash, false);
    }

    #[\Override]
    public function generateAbsolute(string $name, array $arguments = [], array $queryParameters = [], ?string $hash = null, ?string $scheme = null, ?string $host = null): string
    {
        return $this->format($name, $arguments, $queryParameters, $hash, true, $scheme, $host);
    }

    #[\Override]
    public function generateFromCurrent(array $replacedArguments = [], array $queryParameters = [], ?string $hash = null, ?string $fallbackRouteName = null): string
    {
        return $this->generate($fallbackRouteName ?? 'current', $replacedArguments, $queryParameters, $hash);
    }

    #[\Override]
    public function getUriPrefix(): string
    {
        return '';
    }

    #[\Override]
    public function setUriPrefix(string $name): void
    {
    }

    #[\Override]
    public function setDefaultArgument(string $name, \Stringable|string|int|float|bool|null $value): void
    {
    }

    private function format(string $name, array $arguments, array $queryParameters, ?string $hash, bool $absolute, ?string $scheme = null, ?string $host = null): string
    {
        $path = '/' . ltrim($name, '/');
        if ($arguments !== []) {
            $path .= '/' . implode('/', array_map(static fn (mixed $value): string => (string) $value, $arguments));
        }
        if ($queryParameters !== []) {
            $path .= '?' . http_build_query($queryParameters);
        }
        if ($hash !== null) {
            $path .= '#' . $hash;
        }
        if (!$absolute) {
            return $path;
        }

        $scheme ??= 'https';
        $host ??= 'example.test';

        return $scheme . '://' . $host . $path;
    }
}
