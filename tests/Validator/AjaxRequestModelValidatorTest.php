<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Validator\AjaxRequestModelValidator;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;

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
}
