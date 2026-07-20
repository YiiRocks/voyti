<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\User;

use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\ViewData\Shared\AssignableItemRow;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/user/create` screen.
 */
final readonly class CreateViewData
{
    /**
     * @param list<AssignableItemRow> $items
     * @param array<string, list<string>> $errors
     */
    private function __construct(
        public MenuViewData $menu,
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
        RegistrationForm $model,
        array $allItems,
        array $assignedItems,
        array $errors,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): self {
        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            formSubmitUrl: $url->generate('voyti/admin-users-create'),
            usernameValue: $model->username,
            emailValue: $model->email,
            items: AssignableItemRow::fromItems($allItems, $assignedItems),
            errors: $errors,
        );
    }
}
