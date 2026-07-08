<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event\Security;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Event\Security\ResetPasswordEvent;
use Yiisoft\FormModel\FormModel;

final class ResetPasswordEventTest extends TestCase
{

    public function testConstants(): void
    {
        self::assertSame('afterReset', ResetPasswordEvent::AFTER_RESET);
        self::assertSame('beforeTokenValidate', ResetPasswordEvent::BEFORE_TOKEN_VALIDATE);
    }
    public function testConstructorAndGetters(): void
    {
        $token = new UserToken();
        $token->setCode('abc');
        $token->setUserId(1);
        $token->setType(0);
        $token->setCreatedAt(1000);

        $event = new ResetPasswordEvent($token);

        self::assertSame($token, $event->getToken());
        self::assertNull($event->getForm());
    }

    public function testConstructorWithNullForm(): void
    {
        $token = new UserToken();
        $token->setCode('abc');
        $token->setUserId(1);
        $token->setType(0);
        $token->setCreatedAt(1000);

        $event = new ResetPasswordEvent($token);

        self::assertSame($token, $event->getToken());
        self::assertNull($event->getForm());
    }

    public function testUpdateForm(): void
    {
        $token = new UserToken();
        $token->setCode('abc');
        $token->setUserId(1);
        $token->setType(0);
        $token->setCreatedAt(1000);

        $event = new ResetPasswordEvent($token);

        self::assertNull($event->getForm());

        $form = $this->createStub(FormModel::class);
        $result = $event->updateForm($form);

        self::assertSame($event, $result);
        self::assertSame($form, $event->getForm());
    }
}
