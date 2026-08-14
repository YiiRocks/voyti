<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Settings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Settings\ConsentForm;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use Yiisoft\Validator\Rule\Required;

#[AllowMockObjectsWithoutExpectations]
final class ConsentFormTest extends TestCase
{
    use TranslatorMockTrait;

    public function testGetPropertyLabels(): void
    {
        $form = new ConsentForm($this->createTranslator(), 'test', 'voyti.view.delete_account.confirm_label', 'voyti');
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('password', $labels);
    }

    public function testGetRules(): void
    {
        $form = new ConsentForm($this->createTranslator(), 'test', 'voyti.view.delete_account.confirm_label', 'voyti');
        $rules = $form->getRules();
        $this->assertArrayHasKey('password', $rules);
        $this->assertCount(1, $rules['password']);
        $this->assertInstanceOf(Required::class, $rules['password'][0]);
    }

    public function testGetValidationPropertyLabels(): void
    {
        $form = new ConsentForm($this->createTranslator(), 'test', 'voyti.view.delete_account.confirm_label', 'voyti');
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }
}
