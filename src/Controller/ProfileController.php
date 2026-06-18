<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\ProfileRepository;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class ProfileController
{
    use RenderTrait;
    public const PROFILE_VISIBILITY_ADMIN = 1;

    public const PROFILE_VISIBILITY_OWNER = 0;
    public const PROFILE_VISIBILITY_PUBLIC = 3;
    public const PROFILE_VISIBILITY_USERS = 2;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly WebViewRenderer $viewRenderer,
        private readonly UrlGeneratorInterface $url,
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
                    return $this->renderError('voyti.profile.forbidden');
                }
                break;
            case self::PROFILE_VISIBILITY_ADMIN:
                if ($id !== $userId && !$this->isAdmin($identity)) {
                    return $this->renderError('voyti.profile.forbidden');
                }
                break;
            case self::PROFILE_VISIBILITY_USERS:
                if ($userId === null) {
                    return $this->renderError('voyti.profile.forbidden');
                }
                break;
            case self::PROFILE_VISIBILITY_PUBLIC:
                break;
        }

        $profile = $this->profileRepository->findByUserId($id);

        if ($profile === null) {
            return $this->renderError('voyti.profile.not_found');
        }

        return $this->renderView('profile/show', ['profile' => $profile]);
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
