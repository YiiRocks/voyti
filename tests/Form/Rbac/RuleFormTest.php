<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form\Rbac;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Form\Rbac\RuleForm;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class RuleFormTest extends TestCase
{

    public function testConstruct(): void
    {
        $form = new RuleForm($this->createTranslator());
        $this->assertSame('', $form->name);
        $this->assertSame('', $form->class);
        $this->assertSame('', $form->previousName);
    }

    public function testGetAttributeLabels(): void
    {
        $form = new RuleForm($this->createTranslator());
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('name', $labels);
        $this->assertArrayHasKey('class', $labels);
    }

    public function testGetFormName(): void
    {
        $form = new RuleForm($this->createTranslator());
        $this->assertSame('rule', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new RuleForm($this->createTranslator());
        $this->assertSame($form->getAttributeLabels(), $form->getPropertyLabels());
    }

    public function testGetRules(): void
    {
        $form = new RuleForm($this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('class', $rules);
        $this->assertInstanceOf(\Yiisoft\Validator\Rule\Required::class, $rules['name'][0]);
        $this->assertInstanceOf(\Yiisoft\Validator\Rule\Regex::class, $rules['name'][1]);
        $this->assertInstanceOf(\Yiisoft\Validator\Rule\Required::class, $rules['class'][0]);
    }

    public function testSetProperties(): void
    {
        $form = new RuleForm($this->createTranslator());
        $form->name = 'myRule';
        $form->class = 'App\\Rules\\MyRule';
        $form->previousName = 'oldRule';

        $this->assertSame('myRule', $form->name);
        $this->assertSame('App\\Rules\\MyRule', $form->class);
        $this->assertSame('oldRule', $form->previousName);
    }
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(
            fn (string $id) => $id,
        );
        return $translator;
    }
}
