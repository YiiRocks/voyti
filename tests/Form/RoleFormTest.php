<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Stringable;
use YiiRocks\Voyti\Form\Rbac\RoleForm;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\TranslatorInterface;

final class RoleFormTest extends TestCase
{
    public function testConstructorPassesTranslatorToParent(): void
    {
        $form = $this->createForm();

        $this->assertSame(
            ['name' => 'voyti.view.name_label'],
            ['name' => $form->getAttributeLabels()['name']],
        );
    }

    public function testGetFormNameAndType(): void
    {
        $form = $this->createForm();

        $this->assertSame('role', $form->getFormName());
        $this->assertSame('role', $form->getType());
    }

    private function createForm(): RoleForm
    {
        return new RoleForm($this->createTranslator());
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
