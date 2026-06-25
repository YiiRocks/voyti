<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator\Rbac;

use Yiisoft\Rbac\RuleInterface;
use Yiisoft\Validator\Result;

final class RuleExistsValidator
{
    public function validate(?string $ruleName): Result
    {
        $result = new Result();

        if ($ruleName === null || $ruleName === '') {
            return $result;
        }

        if (!class_exists($ruleName)) {
            $result->addError("Rule class '{$ruleName}' does not exist.");
            return $result;
        }

        if (!is_subclass_of($ruleName, RuleInterface::class)) {
            $result->addError("Class '{$ruleName}' must implement RuleInterface.");
        }

        return $result;
    }
}
