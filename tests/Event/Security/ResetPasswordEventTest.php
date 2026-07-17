<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event\Security;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\Security\ResetPasswordEvent;
use YiiRocks\Voyti\Model\UserToken;

final class ResetPasswordEventTest extends TestCase
{

    public function testConstructorAndGetters(): void
    {
        $token = new UserToken();
        $token->setCode('abc');
        $token->setUserId(1);
        $token->setType(0);
        $token->setCreatedAt(1000);

        $event = new ResetPasswordEvent($token);

        self::assertSame($token, $event->getToken());
    }
}
