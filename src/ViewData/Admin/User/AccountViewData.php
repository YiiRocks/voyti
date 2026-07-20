<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\User;

use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ViewData\Shared\AssignableItemRow;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/user/_account` screen.
 */
final readonly class AccountViewData
{
    /**
     * @param list<AssignableItemRow> $items
     * @param array<string, list<string>> $errors
     */
    private function __construct(
        public MenuViewData $menu,
        public string $title,
        public string $formSubmitUrl,
        public string $usernameValue,
        public string $emailValue,
        public array $items,
        public array $errors,
    ) {}

    /**
     * @param array<string, mixed> $allItems keyed by item name
     * @param list<string> $assignedItems
     * @param array<string, list<string>> $errors
     */
    public static function create(
        User $user,
        SettingsForm $model,
        array $allItems,
        array $assignedItems,
        array $errors,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): self {
        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            title: $translator->translate('voyti.view.admin.update_user_title', ['username' => $user->getUsername()]),
            formSubmitUrl: $url->generate('voyti/admin-users-update', ['id' => $user->getId()]),
            usernameValue: $model->username,
            emailValue: $model->email,
            items: AssignableItemRow::fromItems($allItems, $assignedItems),
            errors: $errors,
        );
    }
}
