<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Rbac;

/**
 * A single user assigned to a role/permission, shown on the `admin/rbac/update` screen.
 */
final readonly class AssignedUserRow
{
    public function __construct(
        public string $id,
        public string $username,
    ) {}
}
