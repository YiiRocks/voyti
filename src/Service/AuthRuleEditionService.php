<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\RuleInterface;
use YiiRocks\Voyti\Form\RuleForm;

final class AuthRuleEditionService
{
    public function __construct(
        private readonly ItemsStorageInterface $itemsStorage,
    ) {
    }

    public function create(RuleForm $form): bool
    {
        if (!class_exists($form->class)) {
            return false;
        }

        $reflection = new \ReflectionClass($form->class);
        if (!$reflection->implementsInterface(RuleInterface::class)) {
            return false;
        }

        return true;
    }

    public function update(RuleForm $form): bool
    {
        if ($form->previousName !== '' && $form->previousName !== $form->class) {
            $this->renameRuleReferences($form->previousName, $form->class);
        }

        return $this->create($form);
    }

    public function remove(string $ruleClass): void
    {
        foreach ($this->itemsStorage->getAll() as $item) {
            if ($item->getRuleName() === $ruleClass) {
                $updated = $item->withRuleName(null);
                if ($item instanceof \Yiisoft\Rbac\Role) {
                    $this->itemsStorage->update($item->getName(), $updated);
                } else {
                    $this->itemsStorage->update($item->getName(), $updated);
                }
            }
        }
    }

    private function renameRuleReferences(string $oldClass, string $newClass): void
    {
        foreach ($this->itemsStorage->getAll() as $item) {
            if ($item->getRuleName() === $oldClass) {
                $updated = $item->withRuleName($newClass);
                $this->itemsStorage->update($item->getName(), $updated);
            }
        }
    }
}
