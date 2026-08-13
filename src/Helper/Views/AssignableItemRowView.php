<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper\Views;

/**
 * Builds RBAC role/permission checkbox rows for user assignment forms and RBAC item child pickers.
 */
final class AssignableItemRowView
{
    /**
     * @param array<string, mixed> $items keyed by item name
     * @param list<string> $assignedNames
     *
     * @return list<array{name: string, checked: bool}>
     */
    public static function fromItems(array $items, array $assignedNames): array
    {
        return array_map(
            static fn(string $name): array => ['name' => $name, 'checked' => in_array($name, $assignedNames, true)],
            array_keys($items),
        );
    }
}
