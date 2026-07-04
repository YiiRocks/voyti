<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Stringable;
use YiiRocks\Voyti\Form\Rbac\PermissionForm;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

final class PermissionFormTest extends TestCase
{
    public function testGetAttributeLabelsReturnsTranslatedLabelForEachField(): void
    {
        $form = $this->createForm();

        $this->assertSame(
            [
                'name' => 'voyti.view.name_label',
                'description' => 'voyti.view.description_label',
                'children' => 'voyti.view.children_header',
                'rule' => 'voyti.view.rule.class_label',
            ],
            $form->getAttributeLabels(),
        );
    }

    public function testGetFormNameAndType(): void
    {
        $form = $this->createForm();

        $this->assertSame('permission', $form->getFormName());
        $this->assertSame('permission', $form->getType());
    }

    public function testGetPropertyLabelFallsBackToParentForUnknownProperty(): void
    {
        $form = $this->createForm();

        $this->assertNotSame('voyti.view.name_label', $form->getPropertyLabel('itemName'));
    }

    public function testGetPropertyLabelUsesAttributeLabelForKnownProperty(): void
    {
        $form = $this->createForm();

        $this->assertSame('voyti.view.name_label', $form->getPropertyLabel('name'));
        $this->assertSame('voyti.view.description_label', $form->getPropertyLabel('description'));
    }

    public function testGetRules(): void
    {
        $form = $this->createForm();
        $rules = $form->getRules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertCount(3, $rules['name']);
        $this->assertInstanceOf(Required::class, $rules['name'][0]);
        $this->assertInstanceOf(Regex::class, $rules['name'][1]);
        $this->assertInstanceOf(Length::class, $rules['name'][2]);
        $this->assertSame(1, $rules['name'][2]->getMin());
        $this->assertSame(126, $rules['name'][2]->getMax());
        $this->assertArrayHasKey('description', $rules);
        $this->assertCount(1, $rules['description']);
        $this->assertInstanceOf(Length::class, $rules['description'][0]);
        $this->assertSame(191, $rules['description'][0]->getMax());
    }

    private function createForm(): PermissionForm
    {
        return new PermissionForm($this->createTranslator());
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
            public function getLocale(): string
            {
                return 'en';
            }

            #[\Override]
            public function setLocale(string $locale): static
            {
                return $this;
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
