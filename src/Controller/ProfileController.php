<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\ViewRenderer;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\ProfileRepository;

final class ProfileController
{
    public const PROFILE_VISIBILITY_OWNER = 0;
    public const PROFILE_VISIBILITY_ADMIN = 1;
    public const PROFILE_VISIBILITY_USERS = 2;
    public const PROFILE_VISIBILITY_PUBLIC = 3;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ViewRenderer $viewRenderer,
        private readonly ProfileRepository $profileRepository,
        private readonly AuthHelper $authHelper,
        private readonly ModuleConfig $config,
    ) {
    }

    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        $userId = $identity?->getId();

        switch ($this->config->profileVisibility) {
            case self::PROFILE_VISIBILITY_OWNER:
                if ($userId === null || $id !== $userId) {
                    return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.profile.forbidden'), 'translator' => $this->translator]);
                }
                break;
            case self::PROFILE_VISIBILITY_ADMIN:
                if ($id !== $userId && !$this->isAdmin($identity)) {
                    return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.profile.forbidden'), 'translator' => $this->translator]);
                }
                break;
            case self::PROFILE_VISIBILITY_USERS:
                if ($userId === null) {
                    return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.profile.forbidden'), 'translator' => $this->translator]);
                }
                break;
            case self::PROFILE_VISIBILITY_PUBLIC:
                break;
        }

        $profile = $this->profileRepository->findByUserId($id);

        if ($profile === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.profile.not_found'), 'translator' => $this->translator]);
        }

        return $this->viewRenderer->render('profile/show', ['profile' => $profile]);
    }

    private function isAdmin(?IdentityInterface $identity): bool
    {
        if ($identity === null) {
            return false;
        }
        $id = $identity->getId();
        if ($id === null) {
            return false;
        }
        return $this->authHelper->isAdmin((int) $id);
    }
}
