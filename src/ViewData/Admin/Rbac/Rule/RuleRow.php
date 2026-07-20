<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Rbac\Rule;

/**
 * A single rule row on the `admin/rbac/rule/index` screen.
 */
final readonly class RuleRow
{
    /**
     * @param string $updateUrl a link (GET) to the update screen, not a form target
     * @param string $formSubmitUrl the delete form's POST target
     */
    public function __construct(
        public string $name,
        public string $updateUrl,
        public string $formSubmitUrl,
    ) {}
}
