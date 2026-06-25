<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator\Rbac;

use Yiisoft\Rbac\RuleInterface;
use Yiisoft\Validator\Result;

final class RuleValidator
{
    public function validate(string $className): Result
    {
        $result = new Result();

        if (!class_exists($className)) {
            $result->addError("Class '{$className}' does not exist.");
            return $result;
        }

        if (!is_subclass_of($className, RuleInterface::class)) {
            $result->addError("Class '{$className}' must implement RuleInterface.");
        }

        return $result;
    }
}
