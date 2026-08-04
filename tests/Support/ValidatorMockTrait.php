<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use PHPUnit\Framework\MockObject\MockObject;
use Yiisoft\FormModel\FormModelInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;

trait ValidatorMockTrait
{
    /**
     * Mocks ValidatorInterface so FormHydrator's real Hydrator still populates the form for real, without paying
     * for real attribute-based rule execution. Mirrors what the real Validator does for a FormModelInterface -
     * calling processValidationResult() - since FormModel::addError() requires it to have run first.
     */
    private function mockValidValidator(): MockObject&ValidatorInterface
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturnCallback(static function (FormModelInterface $model): Result {
            $result = new Result();
            $model->processValidationResult($result);

            return $result;
        });

        return $validator;
    }
}
