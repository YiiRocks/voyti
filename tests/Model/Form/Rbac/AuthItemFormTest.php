<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Rbac;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Rbac\AuthItemForm;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

final class AuthItemFormTest extends TestCase
{
    use TranslatorMockTrait;

    public function testDefaultPropertyValues(): void
    {
        $form = $this->createForm();
        $this->assertSame('', $form->name);
        $this->assertSame('', $form->description);
        $this->assertSame([], $form->children);
        $this->assertSame('', $form->itemName);
        $this->assertNull($form->rule);
    }

    public function testGetFormNameReturnsType(): void
    {
        $form = $this->createForm('permission');
        $this->assertSame('permission', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = $this->createForm();
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('name', $labels);
        $this->assertArrayHasKey('description', $labels);
        $this->assertArrayHasKey('children', $labels);
        $this->assertArrayHasKey('rule', $labels);
    }

    public function testGetRules(): void
    {
        $form = $this->createForm();
        $rules = $form->getRules();
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertInstanceOf(Required::class, $rules['name'][0]);
        $this->assertInstanceOf(Regex::class, $rules['name'][1]);
        $this->assertInstanceOf(Length::class, $rules['name'][2]);
        $this->assertInstanceOf(Length::class, $rules['description'][0]);

        $nameLength = $rules['name'][2];
        self::assertSame(1, $this->readPrivate($nameLength, 'min'));
        self::assertSame(126, $this->readPrivate($nameLength, 'max'));

        $descLength = $rules['description'][0];
        self::assertSame(191, $this->readPrivate($descLength, 'max'));
    }

    public function testGetTypeReturnsType(): void
    {
        $form = $this->createForm('role');
        $this->assertSame('role', $form->getType());
    }

    public function testGetValidationPropertyLabelsMatchesPropertyLabels(): void
    {
        $form = $this->createForm();
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }

    public function testSetProperties(): void
    {
        $form = $this->createForm();
        $form->name = 'admin';
        $form->description = 'Admin role';
        $form->children = ['createPost', 'editPost'];
        $form->rule = 'Yiisoft\Rbac\CompositeRule';
        $form->itemName = 'old_name';

        $this->assertSame('admin', $form->name);
        $this->assertSame('Admin role', $form->description);
        $this->assertSame(['createPost', 'editPost'], $form->children);
        $this->assertSame('Yiisoft\Rbac\CompositeRule', $form->rule);
        $this->assertSame('old_name', $form->itemName);
    }

    private function createForm(string $type = 'authItem'): AuthItemForm
    {
        return new AuthItemForm($this->createTranslator(), $type);
    }

    private function readPrivate(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object::class, $property);
        return $reflection->getValue($object);
    }
}
