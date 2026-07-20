<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Rbac;

use YiiRocks\Voyti\Model\Form\Rbac\AuthItemForm;
use YiiRocks\Voyti\ViewData\Shared\AssignableItemRow;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/rbac/create` screen (shared by roles and permissions).
 */
final readonly class CreateViewData
{
    /**
     * @param list<AssignableItemRow> $children
     * @param array<string, list<string>> $errors
     */
    private function __construct(
        public MenuViewData $menu,
        public string $title,
        public string $formSubmitUrl,
        public array $children,
        public array $errors,
    ) {}

    /**
     * @param array<string, mixed> $availableChildren keyed by item name
     * @param array<string, list<string>> $errors
     */
    public static function create(
        string $itemType,
        AuthItemForm $model,
        array $availableChildren,
        array $errors,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): self {
        /** @var list<string> $selectedChildren */
        $selectedChildren = $model->children;

        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            title: $translator->translate('voyti.view.' . $itemType . '.create_title'),
            formSubmitUrl: $url->generate('voyti/admin-rbac-' . $itemType . 's-create'),
            children: AssignableItemRow::fromItems($availableChildren, $selectedChildren),
            errors: $errors,
        );
    }
}
