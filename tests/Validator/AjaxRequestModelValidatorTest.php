<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator;

use PHPUnit\Framework\TestCase;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use YiiRocks\Voyti\Validator\AjaxRequestModelValidator;

final class AjaxRequestModelValidatorTest extends TestCase
{
    public function testValidateDelegatesToInjectedValidator(): void
    {
        $form = $this->createStub(FormModel::class);
        $validator = $this->createMock(ValidatorInterface::class);

        $result = new Result();
        $validator
            ->expects($this->once())
            ->method('validate')
            ->with($form)
            ->willReturn($result);

        $ajaxValidator = new AjaxRequestModelValidator($form, $validator);

        $returnedResult = $ajaxValidator->validate();
        $this->assertSame($result, $returnedResult);
    }

    public function testValidateReturnsResultInstance(): void
    {
        $form = $this->createStub(FormModel::class);
        $validator = $this->createMock(ValidatorInterface::class);

        $result = new Result();
        $validator
            ->method('validate')
            ->with($form)
            ->willReturn($result);

        $ajaxValidator = new AjaxRequestModelValidator($form, $validator);

        $this->assertInstanceOf(Result::class, $ajaxValidator->validate());
    }
}
