<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\User\FormEvent;
use Yiisoft\FormModel\FormModel;

final class FormEventTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $form = $this->createStub(FormModel::class);
        $event = new FormEvent($form);

        $this->assertSame($form, $event->getForm());
    }
}
