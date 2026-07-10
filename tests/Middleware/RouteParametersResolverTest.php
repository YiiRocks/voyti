<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Middleware\RouteParametersResolver;
use Yiisoft\Router\CurrentRoute;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class RouteParametersResolverTest extends TestCase
{

    public function testResolveReturnsEmptyArrayWhenNoArguments(): void
    {
        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->expects(self::once())->method('getArguments')->willReturn([]);

        $resolver = $this->createResolver($currentRoute);

        $result = $resolver->resolve([], $this->createMock(ServerRequestInterface::class));

        self::assertSame([], $result);
    }

    public function testResolveSkipsParametersNotInRouteArguments(): void
    {
        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->expects(self::once())->method('getArguments')->willReturn(['id' => '1']);

        $resolver = $this->createResolver($currentRoute);

        $param1 = new \ReflectionParameter(function (int $id) {
        }, 'id');
        $param2 = new \ReflectionParameter(function (string $name) {
        }, 'name');

        $result = $resolver->resolve(
            ['id' => $param1, 'name' => $param2],
            $this->createMock(ServerRequestInterface::class),
        );

        self::assertSame(['id' => 1], $result);
    }

    public function testResolveWithBoolParameter(): void
    {
        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->expects(self::once())->method('getArguments')->willReturn(['active' => '1']);

        $resolver = $this->createResolver($currentRoute);

        $param = new \ReflectionParameter(function (bool $active) {
        }, 'active');

        $result = $resolver->resolve(['active' => $param], $this->createMock(ServerRequestInterface::class));

        self::assertSame(['active' => true], $result);
    }

    public function testResolveWithFloatParameter(): void
    {
        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->expects(self::once())->method('getArguments')->willReturn(['price' => '19.99']);

        $resolver = $this->createResolver($currentRoute);

        $param = new \ReflectionParameter(function (float $price) {
        }, 'price');

        $result = $resolver->resolve(['price' => $param], $this->createMock(ServerRequestInterface::class));

        self::assertSame(['price' => 19.99], $result);
    }

    public function testResolveWithIntParameter(): void
    {
        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->expects(self::once())->method('getArguments')->willReturn(['id' => '42']);

        $resolver = $this->createResolver($currentRoute);

        $param = new \ReflectionParameter(function (int $id) {
        }, 'id');

        $result = $resolver->resolve(['id' => $param], $this->createMock(ServerRequestInterface::class));

        self::assertSame(['id' => 42], $result);
    }

    public function testResolveWithMixedTypeKeepsRawValue(): void
    {
        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->expects(self::once())->method('getArguments')->willReturn(['value' => 'raw']);

        $resolver = $this->createResolver($currentRoute);

        $param = new \ReflectionParameter(function (mixed $value) {
        }, 'value');

        $result = $resolver->resolve(['value' => $param], $this->createMock(ServerRequestInterface::class));

        self::assertSame(['value' => 'raw'], $result);
    }

    public function testResolveWithNonBuiltinType(): void
    {
        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->expects(self::once())->method('getArguments')->willReturn(['user' => '42']);

        $resolver = $this->createResolver($currentRoute);

        $param = new \ReflectionParameter(function (\stdClass $user) {
        }, 'user');

        $result = $resolver->resolve(['user' => $param], $this->createMock(ServerRequestInterface::class));

        self::assertSame([], $result);
    }

    public function testResolveWithStringParameter(): void
    {
        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->expects(self::once())->method('getArguments')->willReturn(['slug' => 'my-page']);

        $resolver = $this->createResolver($currentRoute);

        $param = new \ReflectionParameter(function (string $slug) {
        }, 'slug');

        $result = $resolver->resolve(['slug' => $param], $this->createMock(ServerRequestInterface::class));

        self::assertSame(['slug' => 'my-page'], $result);
    }

    public function testResolveWithUntypedParameter(): void
    {
        $currentRoute = $this->createMock(CurrentRoute::class);
        $currentRoute->expects(self::once())->method('getArguments')->willReturn(['value' => 'something']);

        $resolver = $this->createResolver($currentRoute);

        $fn = function ($value) {
        };
        $param = new \ReflectionParameter($fn, 'value');

        $result = $resolver->resolve(['value' => $param], $this->createMock(ServerRequestInterface::class));

        self::assertSame(['value' => 'something'], $result);
    }
    private function createResolver(?CurrentRoute $currentRoute = null): RouteParametersResolver
    {
        return new RouteParametersResolver(
            $currentRoute ?? $this->createMock(CurrentRoute::class),
        );
    }
}
