<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Rbac;

use YiiRocks\Voyti\Form\Rbac\RuleForm;
use YiiRocks\Voyti\Validator\Rbac\RuleValidator;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Role;

final class RuleEditionService
{
    public function __construct(
        private readonly ItemsStorageInterface $itemsStorage,
        private readonly RuleValidator $ruleValidator,
    ) {
    }

    public function create(RuleForm $form): bool
    {
        return $this->ruleValidator->validate($form->class)->isValid();
    }

    public function remove(string $ruleClass): void
    {
        foreach ($this->itemsStorage->getAll() as $item) {
            if ($item->getRuleName() === $ruleClass) {
                $updated = $item->withRuleName(null);
                if ($item instanceof Role) {
                    $this->itemsStorage->update($item->getName(), $updated);
                } else {
                    $this->itemsStorage->update($item->getName(), $updated);
                }
            }
        }
    }

    public function update(RuleForm $form): bool
    {
        if ($form->previousName !== '' && $form->previousName !== $form->class) {
            $this->renameRuleReferences($form->previousName, $form->class);
        }

        return $this->create($form);
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
