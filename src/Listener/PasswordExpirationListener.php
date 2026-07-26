<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Service\Password\ExpireService;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Listens for {@see AfterLoginEvent} and checks whether the user's password has expired, queuing
 * a warning flash message if so.
 */
final readonly class PasswordExpirationListener
{
    public function __construct(
        private ExpireService $passwordExpireService,
        private TranslatorInterface $translator,
        private ?FlashInterface $flash = null,
    ) {}

    public function onAfterLogin(AfterLoginEvent $event): void
    {
        $user = $event->getUser();
        if ($this->passwordExpireService->isExpired($user)) {
            $this->flash?->set(
                FlashType::WARNING,
                $this->translator->translate('voyti.security.password_expired', category: 'voyti'),
            );
        }
    }
}
