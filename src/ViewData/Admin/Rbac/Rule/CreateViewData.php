<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Rbac\Rule;

use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/rbac/rule/create` screen.
 */
final readonly class CreateViewData
{
    /**
     * @param array<string, list<string>> $errors
     */
    private function __construct(
        public MenuViewData $menu,
        public string $formSubmitUrl,
        public array $errors,
    ) {}

    /**
     * @param array<string, list<string>> $errors
     */
    public static function create(array $errors, UrlGeneratorInterface $url, TranslatorInterface $translator): self
    {
        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            formSubmitUrl: $url->generate('voyti/admin-rbac-rules-create'),
            errors: $errors,
        );
    }
}
