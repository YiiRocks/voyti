<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use Yiisoft\Translator\TranslatorInterface;

trait TranslatorMockTrait
{
    protected function createTranslator(string $locale = 'en'): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(fn(string $id) => $id);
        return $translator;
    }
}
