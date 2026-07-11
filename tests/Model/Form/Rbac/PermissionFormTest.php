<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Rbac;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Rbac\PermissionForm;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class PermissionFormTest extends TestCase
{

    public function testConstruct(): void
    {
        $form = new PermissionForm($this->createTranslator());
        $this->assertInstanceOf(PermissionForm::class, $form);
    }

    public function testGetAttributeLabels(): void
    {
        $form = new PermissionForm($this->createTranslator());
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('name', $labels);
        $this->assertArrayHasKey('description', $labels);
        $this->assertArrayHasKey('children', $labels);
        $this->assertArrayHasKey('rule', $labels);
    }

    public function testGetFormName(): void
    {
        $form = new PermissionForm($this->createTranslator());
        $this->assertSame('permission', $form->getFormName());
    }

    public function testGetType(): void
    {
        $form = new PermissionForm($this->createTranslator());
        $this->assertSame('permission', $form->getType());
    }

    public function testSetProperties(): void
    {
        $form = new PermissionForm($this->createTranslator());
        $form->name = 'edit-post';
        $form->description = 'Edit post permission';
        $this->assertSame('edit-post', $form->name);
        $this->assertSame('Edit post permission', $form->description);
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
