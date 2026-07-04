<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class ProfileController
{
    use RenderTrait;
    public const int PROFILE_VISIBILITY_ADMIN = 1;

    public const int PROFILE_VISIBILITY_OWNER = 0;
    public const int PROFILE_VISIBILITY_PUBLIC = 3;
    public const int PROFILE_VISIBILITY_USERS = 2;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private UserProfileRepository $userProfileRepository,
        private UserRepository $userRepository,
        private AuthHelper $authHelper,
        private ModuleConfig $config,
        private CurrentUser $currentUser,
    ) {
    }

    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        $userId = $identity instanceof IdentityInterface ? $identity->getId() : null;

        $forbidden = match ($this->config->profileVisibility) {
            self::PROFILE_VISIBILITY_OWNER => $userId === null || (string) $id !== $userId,
            self::PROFILE_VISIBILITY_ADMIN => (string) $id !== $userId && !$this->isAdmin($identity),
            self::PROFILE_VISIBILITY_USERS => $userId === null,
            default => false,
        };

        if ($forbidden) {
            return $this->renderError('voyti.userProfile.forbidden');
        }

        $userProfile = $this->userProfileRepository->findByUserId($id);

        if ($userProfile === null) {
            return $this->renderError('voyti.userProfile.not_found');
        }

        $user = $this->userRepository->findById($id);

        return $this->renderView('profile/show', ['user' => $user, 'userProfile' => $userProfile]);
    }

    protected function viewPath(): string
    {
        return $this->config->viewPath;
    }

    private function isAdmin(IdentityInterface $identity): bool
    {
        if ($identity instanceof GuestIdentityInterface) {
            return false;
        }
        $id = $identity->getId();
        if ($id === null) {
            return false;
        }
        return $this->authHelper->isAdmin((int) $id);
    }
}
