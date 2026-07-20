<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Rbac\Rule;

use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/rbac/rule/index` screen.
 */
final readonly class IndexViewData
{
    /**
     * @param list<RuleRow> $rules
     */
    private function __construct(
        public MenuViewData $menu,
        public string $createUrl,
        public array $rules,
    ) {}

    /**
     * @param list<string> $ruleNames
     */
    public static function create(array $ruleNames, UrlGeneratorInterface $url, TranslatorInterface $translator): self
    {
        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            createUrl: $url->generate('voyti/admin-rbac-rules-create'),
            rules: array_map(
                static fn(string $name): RuleRow => new RuleRow(
                    name: $name,
                    updateUrl: $url->generate('voyti/admin-rbac-rules-update', ['name' => $name]),
                    formSubmitUrl: $url->generate('voyti/admin-rbac-rules-delete', ['name' => $name]),
                ),
                $ruleNames,
            ),
        );
    }
}
