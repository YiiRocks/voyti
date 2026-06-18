<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator;

use Yiisoft\Validator\Result;

final class RbacRuleValidator
{
    public function validate(string $className): Result
    {
        $result = new Result();

        if (!class_exists($className)) {
            $result->addError("Class '{$className}' does not exist.");
            return $result;
        }

        $reflection = new \ReflectionClass($className);
        if (!$reflection->implementsInterface(\Yiisoft\Rbac\RuleInterface::class)) {
            $result->addError("Class '{$className}' must implement RuleInterface.");
        }

        return $result;
    }
}
