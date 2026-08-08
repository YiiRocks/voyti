<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Router\UrlGeneratorInterface;

final class VoytiConfigTest extends TestCase
{
    public function testGetHomeUrlReturnsGeneratedUrl(): void
    {
        $config = VoytiConfigFactory::create(homeRoute: 'app/home');
        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->expects($this->once())->method('generate')->with('app/home')->willReturn('/home');

        self::assertSame('/home', $config->getHomeUrl($url));
    }

    public function testGetHomeUrlThrowsWithFullMessageAndZeroCodeWhenRouteMissing(): void
    {
        $config = VoytiConfigFactory::create(homeRoute: 'nonexistent');
        $previous = new RouteNotFoundException('nonexistent');
        $url = $this->createStub(UrlGeneratorInterface::class);
        $url->method('generate')->willThrowException($previous);

        try {
            $config->getHomeUrl($url);
            self::fail('Expected a LogicException for the unregistered homeRoute.');
        } catch (LogicException $exception) {
            self::assertSame(
                '"homeRoute" is set to "nonexistent", but no such route is registered. '
                . 'Configure "homeRoute" in the "yiirocks/voyti" params to point to a route the '
                . 'application actually defines.',
                $exception->getMessage(),
            );
            self::assertSame(0, $exception->getCode());
            self::assertSame($previous, $exception->getPrevious());
        }
    }
}
