<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
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
        private readonly UserProfileRepository $userProfileRepository,
        private readonly AuthHelper $authHelper,
        private readonly ModuleConfig $config,
        private readonly CurrentUser $currentUser,
    ) {
    }

    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        $userId = $identity instanceof IdentityInterface ? $identity->getId() : null;

        switch ($this->config->profileVisibility) {
            case self::PROFILE_VISIBILITY_OWNER:
                if ($userId === null || (string) $id !== $userId) {
                    return $this->renderError('voyti.userProfile.forbidden');
                }
                break;
            case self::PROFILE_VISIBILITY_ADMIN:
                if ((string) $id !== $userId && !$this->isAdmin($identity)) {
                    return $this->renderError('voyti.userProfile.forbidden');
                }
                break;
            case self::PROFILE_VISIBILITY_USERS:
                if ($userId === null) {
                    return $this->renderError('voyti.userProfile.forbidden');
                }
                break;
            case self::PROFILE_VISIBILITY_PUBLIC:
                break;
        }

        $userProfile = $this->userProfileRepository->findByUserId($id);

        if ($userProfile === null) {
            return $this->renderError('voyti.userProfile.not_found');
        }

        return $this->renderView('userProfile/show', ['userProfile' => $userProfile]);
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
