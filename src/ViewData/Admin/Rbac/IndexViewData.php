<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Rbac;

use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Rbac\Item;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/rbac/index` screen (shared by roles and permissions).
 */
final readonly class IndexViewData
{
    /**
     * @param list<RbacItemRow> $items
     */
    private function __construct(
        public MenuViewData $menu,
        public string $title,
        public string $createLinkLabel,
        public string $createUrl,
        public string $filterUrl,
        public string $filterName,
        public string $filterDescription,
        public array $items,
    ) {}

    /**
     * @param array<string, Item> $items
     * @param array<string, list<string>> $itemChildren
     */
    public static function create(
        string $itemType,
        array $items,
        array $itemChildren,
        string $filterName,
        string $filterDescription,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): self {
        $routePrefix = 'admin-rbac-' . $itemType . 's';

        $rows = array_map(
            static fn(Item $item): RbacItemRow => new RbacItemRow(
                name: $item->getName(),
                description: $item->getDescription(),
                childrenDisplay: implode(', ', $itemChildren[$item->getName()] ?? []),
                updateUrl: $url->generate('voyti/' . $routePrefix . '-update', ['name' => $item->getName()]),
                formSubmitUrl: $url->generate('voyti/' . $routePrefix . '-delete', ['name' => $item->getName()]),
            ),
            array_values($items),
        );

        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            title: $translator->translate('voyti.view.' . $itemType . '.title'),
            createLinkLabel: $translator->translate('voyti.view.' . $itemType . '.create_link'),
            createUrl: $url->generate('voyti/' . $routePrefix . '-create'),
            filterUrl: $url->generate('voyti/' . $routePrefix),
            filterName: $filterName,
            filterDescription: $filterDescription,
            items: $rows,
        );
    }
}
