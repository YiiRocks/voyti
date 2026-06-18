<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Yiisoft\Validator\Validator;
use YiiRocks\Voyti\Form\RuleForm;

final class RuleFormTest extends TestCase
{
    public function testValidData(): void
    {
        $validator = new Validator();
        $form = new RuleForm();
        $form->name = 'admin';
        $form->class = 'Yiisoft\\Rbac\\Role';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testEmptyName(): void
    {
        $validator = new Validator();
        $form = new RuleForm();
        $form->name = '';
        $form->class = 'Yiisoft\\Rbac\\Role';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('name'));
    }

    public function testEmptyClass(): void
    {
        $validator = new Validator();
        $form = new RuleForm();
        $form->name = 'admin';
        $form->class = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('class'));
    }

    public function testEmptyAll(): void
    {
        $validator = new Validator();
        $form = new RuleForm();

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
    }

    public function testDefaults(): void
    {
        $form = new RuleForm();

        $this->assertSame('', $form->name);
        $this->assertSame('', $form->class);
    }

    public function testPropertyAccess(): void
    {
        $form = new RuleForm();
        $form->name = 'moderator';
        $form->class = 'Yiisoft\\Rbac\\Permission';

        $this->assertSame('moderator', $form->getPropertyValue('name'));
        $this->assertSame('Yiisoft\\Rbac\\Permission', $form->getPropertyValue('class'));
    }
}
