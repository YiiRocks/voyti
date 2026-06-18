<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Stringable;
use YiiRocks\Voyti\Form\Rbac\RuleForm;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Validator;

final class RuleFormTest extends TestCase
{

    public function testDefaults(): void
    {
        $form = $this->createForm();

        $this->assertSame('', $form->name);
        $this->assertSame('', $form->class);
    }

    public function testEmptyAll(): void
    {
        $validator = new Validator();
        $form = $this->createForm();

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
    }

    public function testEmptyClass(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->name = 'admin';
        $form->class = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('class'));
    }

    public function testEmptyName(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->name = '';
        $form->class = 'Yiisoft\\Rbac\\Role';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('name'));
    }

    public function testPropertyAccess(): void
    {
        $form = $this->createForm();
        $form->name = 'moderator';
        $form->class = 'Yiisoft\\Rbac\\Permission';

        $this->assertSame('moderator', $form->getPropertyValue('name'));
        $this->assertSame('Yiisoft\\Rbac\\Permission', $form->getPropertyValue('class'));
    }
    public function testValidData(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->name = 'admin';
        $form->class = 'Yiisoft\\Rbac\\Role';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    private function createForm(): RuleForm
    {
        return new RuleForm($this->createTranslator());
    }
    private function createTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            #[\Override]
            public function addCategorySources(CategorySource ...$categories): static
            {
                return $this;
            }
            #[\Override]
            public function setLocale(string $locale): static
            {
                return $this;
            }
            #[\Override]
            public function getLocale(): string
            {
                return 'en';
            }
            #[\Override]
            public function translate(string|Stringable $id, array $parameters = [], ?string $category = null, ?string $locale = null): string
            {
                return (string) $id;
            }
            #[\Override]
            public function withDefaultCategory(string $category): static
            {
                return $this;
            }
            #[\Override]
            public function withLocale(string $locale): static
            {
                return $this;
            }
        };
    }
}
