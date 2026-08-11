<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Middleware\PasswordAgeEnforceMiddleware;
use YiiRocks\Voyti\Middleware\RememberMeMiddleware;
use YiiRocks\Voyti\Middleware\SessionRevocationEnforceMiddleware;
use YiiRocks\Voyti\VoytiRoutes;
use Yiisoft\Csrf\CsrfTokenMiddleware;
use Yiisoft\Session\SessionMiddleware;

final class VoytiRoutesTest extends TestCase
{
    public function testWebMiddlewareAppendsPasswordAgeWhenMaxAgeConfigured(): void
    {
        self::assertSame(
            [
                SessionMiddleware::class,
                RememberMeMiddleware::class,
                CsrfTokenMiddleware::class,
                SessionRevocationEnforceMiddleware::class,
                PasswordAgeEnforceMiddleware::class,
            ],
            VoytiRoutes::webMiddleware(['maxPasswordAge' => 3600]),
        );
    }

    public function testWebMiddlewareOmitsPasswordAgeWhenMaxAgeNotConfigured(): void
    {
        self::assertSame(
            [
                SessionMiddleware::class,
                RememberMeMiddleware::class,
                CsrfTokenMiddleware::class,
                SessionRevocationEnforceMiddleware::class,
            ],
            VoytiRoutes::webMiddleware([]),
        );
    }
}
