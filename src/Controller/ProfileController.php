<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\ProfileVisibility;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class ProfileController
{
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
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
            ProfileVisibility::OWNER => $userId === null || (string) $id !== $userId,
            ProfileVisibility::ADMIN => (string) $id !== $userId && !$this->isAdmin($identity),
            ProfileVisibility::USERS => $userId === null,
            ProfileVisibility::PUBLIC => false,
        };

        if ($forbidden) {
            return $this->renderError('voyti.userProfile.forbidden');
        }

        $userProfile = UserProfile::findByUserId($id);

        if ($userProfile === null) {
            return $this->renderError('voyti.userProfile.not_found');
        }

        $user = User::findById($id);

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
