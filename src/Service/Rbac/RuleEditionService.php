<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Rbac;

use YiiRocks\Voyti\Model\Form\Rbac\RuleForm;
use YiiRocks\Voyti\Validator\Rbac\RuleValidator;
use Yiisoft\Rbac\ItemsStorageInterface;

/**
 * Manages RBAC rule class references on authorization items: validates rule classes via
 * {@see RuleValidator}, and renames or clears references across {@see ItemsStorageInterface} items
 * when a rule class is renamed or removed.
 */
final readonly class RuleEditionService
{
    public function __construct(
        private ItemsStorageInterface $itemsStorage,
        private RuleValidator $ruleValidator,
    ) {}

    public function create(RuleForm $form): bool
    {
        return $this->ruleValidator->validate($form->class)->isValid();
    }

    public function remove(string $ruleClass): void
    {
        foreach ($this->itemsStorage->getAll() as $item) {
            if ($item->getRuleName() === $ruleClass) {
                $this->itemsStorage->update($item->getName(), $item->withRuleName(null));
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
