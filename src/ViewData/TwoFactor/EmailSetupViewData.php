<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\TwoFactor;

use YiiRocks\Voyti\Model\User;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Data for the `two-factor/_email` setup fragment.
 */
final readonly class EmailSetupViewData
{
    private function __construct(
        public bool $emailCodeSent,
        public string $userEmail,
        public string $sendCodeUrl,
        public string $enableUrl,
    ) {}

    public static function create(User $user, bool $emailCodeSent, UrlGeneratorInterface $url): self
    {
        return new self(
            emailCodeSent: $emailCodeSent,
            userEmail: $user->getEmail(),
            sendCodeUrl: $url->generate('voyti/two-factor-send-email-code'),
            enableUrl: $url->generate('voyti/two-factor-enable'),
        );
    }
}
