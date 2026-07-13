<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Rbac;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Rbac\RoleForm;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class RoleFormTest extends TestCase
{
    use TranslatorMockTrait;

    public function testConstruct(): void
    {
        $form = new RoleForm($this->createTranslator());
        $this->assertInstanceOf(RoleForm::class, $form);
    }

    public function testGetAttributeLabels(): void
    {
        $form = new RoleForm($this->createTranslator());
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('name', $labels);
        $this->assertArrayHasKey('description', $labels);
        $this->assertArrayHasKey('children', $labels);
        $this->assertArrayHasKey('rule', $labels);
    }

    public function testGetFormName(): void
    {
        $form = new RoleForm($this->createTranslator());
        $this->assertSame('role', $form->getFormName());
    }

    public function testGetType(): void
    {
        $form = new RoleForm($this->createTranslator());
        $this->assertSame('role', $form->getType());
    }

    public function testSetProperties(): void
    {
        $form = new RoleForm($this->createTranslator());
        $form->name = 'admin';
        $form->description = 'Administrator role';
        $this->assertSame('admin', $form->name);
        $this->assertSame('Administrator role', $form->description);
    }
}
