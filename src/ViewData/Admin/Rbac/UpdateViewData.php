<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Rbac;

use YiiRocks\Voyti\Model\Form\Rbac\AuthItemForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ViewData\Shared\AssignableItemRow;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/rbac/update` screen (shared by roles and permissions).
 */
final readonly class UpdateViewData
{
    /**
     * @param list<AssignableItemRow> $children
     * @param list<AssignedUserRow> $assignedUsers
     * @param array<string, list<string>> $errors
     */
    private function __construct(
        public MenuViewData $menu,
        public string $title,
        public string $formSubmitUrl,
        public array $children,
        public array $assignedUsers,
        public array $errors,
    ) {}

    /**
     * @param array<string, mixed> $availableChildren keyed by item name
     * @param list<User> $users
     * @param array<string, list<string>> $errors
     */
    public static function create(
        string $itemType,
        AuthItemForm $model,
        array $availableChildren,
        array $users,
        array $errors,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): self {
        /** @var list<string> $selectedChildren */
        $selectedChildren = $model->children;

        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            title: $translator->translate('voyti.view.' . $itemType . '.update_title', ['name' => $model->itemName]),
            formSubmitUrl: $url->generate('voyti/admin-rbac-' . $itemType . 's-update', ['name' => $model->itemName]),
            children: AssignableItemRow::fromItems($availableChildren, $selectedChildren),
            assignedUsers: array_map(
                static fn(User $user): AssignedUserRow => new AssignedUserRow((string) $user->getId(), $user->getUsername()),
                $users,
            ),
            errors: $errors,
        );
    }
}
