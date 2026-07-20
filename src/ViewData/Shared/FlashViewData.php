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
    public function __construct(
        public ?string $warning,
        public ?string $success,
    ) {}

    public static function fromFlash(FlashInterface $flash): self
    {
        return new self(
            warning: self::nonEmpty($flash->get(FlashType::WARNING)),
            success: self::nonEmpty($flash->get(FlashType::SUCCESS)),
        );
    }

    private static function nonEmpty(mixed $value): ?string
    {
        $value = (string) $value;

        return $value === '' ? null : $value;
    }
}
