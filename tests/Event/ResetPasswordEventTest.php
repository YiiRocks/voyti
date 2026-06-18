<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YiiRocks\Voyti\Event\Security\ResetPasswordEvent;

final class ResetPasswordEventTest extends TestCase
{

    public function testConstructAndGettersWithForm(): void
    {
        $token = (new ReflectionClass(\YiiRocks\Voyti\Entity\Token::class))->newInstanceWithoutConstructor();
        $form = $this->createStub(\Yiisoft\FormModel\FormModel::class);
        $event = new ResetPasswordEvent($token, $form);

        $this->assertSame($token, $event->getToken());
        $this->assertSame($form, $event->getForm());
    }
    public function testConstructAndGettersWithNullForm(): void
    {
        $token = (new ReflectionClass(\YiiRocks\Voyti\Entity\Token::class))->newInstanceWithoutConstructor();
        $event = new ResetPasswordEvent($token);

        $this->assertSame($token, $event->getToken());
        $this->assertNull($event->getForm());
    }

    public function testUpdateForm(): void
    {
        $token = (new ReflectionClass(\YiiRocks\Voyti\Entity\Token::class))->newInstanceWithoutConstructor();
        $event = new ResetPasswordEvent($token);

        $form = $this->createStub(\Yiisoft\FormModel\FormModel::class);
        $result = $event->updateForm($form);

        $this->assertSame($form, $event->getForm());
        $this->assertSame($event, $result);
    }
}
