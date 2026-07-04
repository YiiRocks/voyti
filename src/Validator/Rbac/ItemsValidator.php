<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator\Rbac;

use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Validator\Result;

final readonly class ItemsValidator
{
    public function __construct(
        private ItemsStorageInterface $itemsStorage,
    ) {
    }

    /**
     * @param list<string> $items
     */
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
