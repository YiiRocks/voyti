<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event\User;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\User\FormEvent;
use Yiisoft\FormModel\FormModel;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class FormEventTest extends TestCase
{

    public function testConstructorAndGetters(): void
    {
        $form = $this->createMock(FormModel::class);

        $event = new FormEvent($form);

        self::assertSame($form, $event->getForm());
    }
}
