<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Stringable;
use YiiRocks\Voyti\Form\Settings\GdprConsentForm;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\TranslatorInterface;

final class GdprConsentFormTest extends TestCase
{
    public function testGetAttributeLabelsIsPublicAndReturnsTranslatedConsentLabel(): void
    {
        $form = $this->createForm();

        $labels = $form->getAttributeLabels();

        $this->assertSame(
            ['consent' => 'voyti.view.gdpr.consent_label'],
            $labels,
        );
    }

    public function testGetPropertyLabelFallsBackToParentForUnknownProperty(): void
    {
        $form = $this->createForm();

        $this->assertSame('Unknown', $form->getPropertyLabel('unknown'));
    }

    public function testGetPropertyLabelReturnsLabelFromAttributeLabels(): void
    {
        $form = $this->createForm();

        $this->assertSame('voyti.view.gdpr.consent_label', $form->getPropertyLabel('consent'));
    }

    private function createForm(): GdprConsentForm
    {
        return new GdprConsentForm($this->createTranslator());
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
