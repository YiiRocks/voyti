<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Rbac;

/**
 * A single role/permission row on the `admin/rbac/index` screen.
 */
final readonly class RbacItemRow
{
    /**
     * @param string $updateUrl a link (GET) to the update screen, not a form target
     * @param string $formSubmitUrl the delete form's POST target
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $childrenDisplay,
        public string $updateUrl,
        public string $formSubmitUrl,
    ) {}
}
