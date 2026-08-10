<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Shared;

use YiiRocks\Voyti\Helper\FlashType;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * Resolved session flash messages, so templates never need `FlashInterface`/`FlashType`.
 */
final readonly class FlashViewData
{
    public ?string $success;
    public ?string $warning;

    public function __construct(FlashInterface $flash)
    {
        $warning = (string) $flash->get(FlashType::WARNING);
        $this->warning = $warning === '' ? null : $warning;

        $success = (string) $flash->get(FlashType::SUCCESS);
        $this->success = $success === '' ? null : $success;
    }
}
