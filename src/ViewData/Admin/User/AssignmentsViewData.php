<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\User;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/user/_assignments` screen.
 */
final readonly class AssignmentsViewData
{
    /**
     * @param list<string> $assignedItemNames
     * @param list<string> $availableItemNames
     */
    private function __construct(
        public MenuViewData $menu,
        public string $formSubmitUrl,
        public array $assignedItemNames,
        public array $availableItemNames,
    ) {}

    /**
     * @param list<string> $assignedItemNames
     * @param array<string, mixed> $available keyed by item name
     */
    public static function create(
        User $user,
        array $assignedItemNames,
        array $available,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): self {
        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            formSubmitUrl: $url->generate('voyti/admin-users-assignments', ['id' => $user->getId()]),
            assignedItemNames: $assignedItemNames,
            availableItemNames: array_keys($available),
        );
    }
}
