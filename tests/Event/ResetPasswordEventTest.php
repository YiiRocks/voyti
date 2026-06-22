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
        $userToken = (new ReflectionClass(\YiiRocks\Voyti\Entity\UserToken::class))->newInstanceWithoutConstructor();
        $form = $this->createStub(\Yiisoft\FormModel\FormModel::class);
        $event = new ResetPasswordEvent($userToken, $form);

        $this->assertSame($userToken, $event->getToken());
        $this->assertSame($form, $event->getForm());
    }
    public function testConstructAndGettersWithNullForm(): void
    {
        $userToken = (new ReflectionClass(\YiiRocks\Voyti\Entity\UserToken::class))->newInstanceWithoutConstructor();
        $event = new ResetPasswordEvent($userToken);

        $this->assertSame($userToken, $event->getToken());
        $this->assertNull($event->getForm());
    }

    public function testUpdateForm(): void
    {
        $userToken = (new ReflectionClass(\YiiRocks\Voyti\Entity\UserToken::class))->newInstanceWithoutConstructor();
        $event = new ResetPasswordEvent($userToken);

        $form = $this->createStub(\Yiisoft\FormModel\FormModel::class);
        $result = $event->updateForm($form);

        $this->assertSame($form, $event->getForm());
        $this->assertSame($event, $result);
    }
}
