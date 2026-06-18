<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use Yiisoft\FormModel\FormModel;
use YiiRocks\Voyti\Event\FormEvent;

final class FormEventTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $form = $this->createStub(FormModel::class);
        $event = new FormEvent($form);

        $this->assertSame($form, $event->getForm());
    }
}
