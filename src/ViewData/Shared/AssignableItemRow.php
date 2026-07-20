<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Shared;

/**
 * A single RBAC role/permission checkbox row (user assignment forms, RBAC item child pickers).
 */
final readonly class AssignableItemRow
{
    public function __construct(
        public string $name,
        public bool $checked,
    ) {}

    /**
     * @param array<string, mixed> $items keyed by item name
     * @param list<string> $assignedNames
     *
     * @return list<self>
     */
    public static function fromItems(array $items, array $assignedNames): array
    {
        return array_map(
            static fn(string $name): self => new self($name, in_array($name, $assignedNames, true)),
            array_keys($items),
        );
    }
}
