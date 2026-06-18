<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator;

use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\RuleHandlerInterface;
use Yiisoft\Validator\ValidatorInterface;

final class RbacItemsValidator
{
    public function __construct(
        private readonly ItemsStorageInterface $itemsStorage,
    ) {
    }

    public function validate(array $items): Result
    {
        $result = new Result();

        foreach ($items as $itemName) {
            if (!$this->itemsStorage->exists($itemName)) {
                $result->addError("Authorization item '{$itemName}' does not exist.");
            }
        }

        return $result;
    }
}
