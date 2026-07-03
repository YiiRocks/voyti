<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Stringable;
use YiiRocks\Voyti\Form\Settings\GdprDeleteForm;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Validator;

final class GdprDeleteFormTest extends TestCase
{

    public function testDefaults(): void
    {
        $form = $this->createForm();
        $this->assertSame('', $form->password);
    }

    public function testEmptyPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->password = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testGetAttributeLabels(): void
    {
        $form = $this->createForm();
        $labels = $form->getAttributeLabels();

        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('consent', $labels);
        $this->assertSame('voyti.view.current_password_label', $labels['password']);
        $this->assertSame('voyti.view.gdpr.delete_confirm_label', $labels['consent']);
    }

    public function testGetFormName(): void
    {
        $form = $this->createForm();
        $this->assertSame('gdpr-delete', $form->getFormName());
    }

    public function testGetPropertyLabelKnownProperty(): void
    {
        $form = $this->createForm();

        $this->assertSame('voyti.view.current_password_label', $form->getPropertyLabel('password'));
        $this->assertSame('voyti.view.gdpr.delete_confirm_label', $form->getPropertyLabel('consent'));
    }

    public function testGetPropertyLabelUnknownPropertyFallsBackToParent(): void
    {
        $form = $this->createForm();

        $this->assertSame('Unknown', $form->getPropertyLabel('unknown'));
    }

    public function testPropertyAccess(): void
    {
        $form = $this->createForm();
        $form->password = 'mypassword';

        $this->assertSame('mypassword', $form->getPropertyValue('password'));
    }
    public function testUnconsentedFails(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->password = 'currentpassword';
        $form->consent = false;

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('consent'));
    }

    public function testValidData(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->password = 'currentpassword';
        $form->consent = true;

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    private function createForm(): GdprDeleteForm
    {
        return new GdprDeleteForm($this->createTranslator());
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
