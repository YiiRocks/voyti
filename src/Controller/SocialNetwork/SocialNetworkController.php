<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\SocialNetwork;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class SocialNetworkController
{
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private ModuleConfig $config,
        private AuthClientRegistry $authClientRegistry,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private FlashInterface $flash,
    ) {
    }

    public function delete(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti')]);
        }

        $account = null;
        $accounts = UserSocialAccount::findByUserId((int) ($identity->getId() ?? 0));
        foreach ($accounts as $candidate) {
            if ($candidate->getId() === $id) {
                $account = $candidate;
                break;
            }
        }

        if ($account !== null) {
            $account->delete();
            return $this->redirectWithFlash(
                $this->url->generate('voyti/social-network'),
                'voyti.settings.network_disconnected',
            );
        }

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.network_not_found', category: 'voyti')]);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $accounts = UserSocialAccount::findByUserId((int) ($identity->getId() ?? 0));
        $connectedProviders = array_filter(array_map(
            static fn (UserSocialAccount $account): string => $account->getProvider(),
            $accounts,
        ));

        return $this->renderView('social-network/index', [
            'accounts' => $accounts,
            'config' => $this->config,
            'authClients' => $this->authClientRegistry,
            'connectRouteName' => 'voyti/session-connect',
            'excludedProviders' => $connectedProviders,
            'flash' => $this->flash,
        ]);
    }

    protected function viewPath(): string
    {
        return $this->config->viewPath;
    }
}
