<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator;

use Yiisoft\Validator\Result;

final class RbacRuleExistsValidator
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

        $reflection = new \ReflectionClass($ruleName);
        if (!$reflection->implementsInterface(\Yiisoft\Rbac\RuleInterface::class)) {
            $result->addError("Class '{$ruleName}' must implement RuleInterface.");
        }

        return $result;
    }
}
