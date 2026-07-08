<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event\User;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\User\FormEvent;
use Yiisoft\FormModel\FormModel;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class FormEventTest extends TestCase
{

    public function testConstants(): void
    {
        self::assertSame('afterLogin', FormEvent::AFTER_LOGIN);
        self::assertSame('afterRegister', FormEvent::AFTER_REGISTER);
        self::assertSame('afterRequest', FormEvent::AFTER_REQUEST);
        self::assertSame('afterResend', FormEvent::AFTER_RESEND);
        self::assertSame('beforeLogin', FormEvent::BEFORE_LOGIN);
        self::assertSame('beforeRegister', FormEvent::BEFORE_REGISTER);
        self::assertSame('beforeRequest', FormEvent::BEFORE_REQUEST);
        self::assertSame('beforeResend', FormEvent::BEFORE_RESEND);
        self::assertSame('failedLogin', FormEvent::FAILED_LOGIN);
    }
    public function testConstructorAndGetters(): void
    {
        $form = $this->createMock(FormModel::class);

        $event = new FormEvent($form);

        self::assertSame($form, $event->getForm());
    }
}
