<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Widget;

final class SessionStatusWidget
{
    public function render(bool $isActive): string
    {
        if ($isActive) {
            return '<span class="label label-success">Active</span>';
        }
        return '<span class="label label-default">Inactive</span>';
    }
}
