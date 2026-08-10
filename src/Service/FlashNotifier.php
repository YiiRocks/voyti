<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\ToastBootstrap5\FlashToast;
use YiiRocks\ToastBootstrap5\ToastType;
use YiiRocks\Voyti\Helper\FlashType;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * Queues a user message onto the session flash, typed by voyti's own {@see FlashType} vocabulary.
 * When yiirocks/toast-bootstrap5 is installed the message is stored under the keys its toast
 * renderer reads; otherwise it goes to the plain flash bag the fallback alert view reads.
 */
final class FlashNotifier
{
    public function __construct(private FlashInterface $flash) {}

    public function add(string $type, string $message): void
    {
        if (class_exists(FlashToast::class)) {
            (new FlashToast($this->flash))->add(ToastType::from($type), $message);
        } else {
            // Optional-dependency branch: this repo installs the package in dev, so it never runs here.
            // @codeCoverageIgnoreStart
            $this->flash->set($type, $message);
            // @codeCoverageIgnoreEnd
        }
    }
}
