<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator;

use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Validator\Result;

final class RbacRuleNameValidator
{
    public function __construct(
        private readonly ItemsStorageInterface $itemsStorage,
    ) {
    }

    public function validate(string $name, string $previousName = ''): Result
    {
        $result = new Result();

        if ($name !== $previousName) {
            foreach ($this->itemsStorage->getAll() as $item) {
                if ($item->getRuleName() === $name) {
                    $result->addError("Rule with name '{$name}' already exists.");
                    break;
                }
            }
        }

        return $result;
    }
}
