<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Rbac\Rule;

use YiiRocks\Voyti\Model\Form\Rbac\RuleForm;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/rbac/rule/update` screen.
 */
final readonly class UpdateViewData
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
    public static function create(RuleForm $model, array $errors, UrlGeneratorInterface $url, TranslatorInterface $translator): self
    {
        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            formSubmitUrl: $url->generate('voyti/admin-rbac-rules-update', ['name' => $model->previousName]),
            errors: $errors,
        );
    }
}
