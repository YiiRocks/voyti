<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\ExpireService;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;

final readonly class PasswordExpirationListener
{
    public function __construct(
        private ExpireService $passwordExpireService,
        private ModuleConfig $config,
        private TranslatorInterface $translator,
        private ?FlashInterface $flash = null,
    ) {
    }

    public function onAfterLogin(AfterLoginEvent $event): void
    {
        if (!$this->config->enablePasswordExpiration) {
            return;
        }
        $user = $event->getUser();
        if ($this->passwordExpireService->checkPasswordExpiration($user)) {
            $this->flash?->set('warning', $this->translator->translate('voyti.security.password_expired', category: 'voyti'));
        }
    }
}
