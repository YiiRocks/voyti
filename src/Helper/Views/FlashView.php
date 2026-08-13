<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper\Views;

use YiiRocks\Voyti\Helper\FlashType;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * Resolves session flash messages into a view-ready array, so templates never need
 * `FlashInterface`/`FlashType`.
 */
final class FlashView
{
    /**
     * @return array{success: string|null, warning: string|null}
     */
    public static function create(FlashInterface $flash): array
    {
        /** @infection-ignore-all Defensive cast: get() returns array|string|null, and for the string|null messages Voyti stores, the '' and null results are identical after the ternary below. */
        $warning = (string) $flash->get(FlashType::WARNING);
        /** @infection-ignore-all Defensive cast: same equivalence as above for success messages. */
        $success = (string) $flash->get(FlashType::SUCCESS);

        return [
            'warning' => $warning === '' ? null : $warning,
            'success' => $success === '' ? null : $success,
        ];
    }
}
