<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Auth;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\ViewData\Shared\MessageViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\AuthClient\AuthClientInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Replaces `SessionController::auth()`'s body as the success/cancel callbacks wired into
 * {@see \Yiisoft\Yii\AuthClient\AuthAction}: normalizes the provider's attributes, then either logs
 * a guest in via {@see UserSocialAuthenticateService} or links the account to the current user via
 * {@see UserSocialAccountConnectService}.
 *
 * `AuthAction`'s callbacks only receive the {@see AuthClientInterface}, never the incoming
 * `ServerRequestInterface` (it's `final`, so there's no hook to change that) - login metadata that
 * used to come from `$request->getServerParams()` is read from PHP's `$_SERVER` superglobal instead,
 * which is equivalent under the traditional php-fpm/apache SAPI this repo's tooling targets.
 */
final readonly class SocialAuthCallbackService
{
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $url,
        private ModuleConfig $config,
        private SessionInterface $session,
        private FlashInterface $flash,
        private CurrentUser $currentUser,
        private RememberMeCookieService $rememberMeCookieService,
        private PendingSocialAccountService $pendingSocialAccountService,
        private UserSocialAuthenticateService $socialAuthenticateService,
        private UserSocialAccountConnectService $socialAccountConnectService,
        private SocialUserAttributesNormalizer $normalizer,
    ) {}

    public function handleCancel(AuthClientInterface $client): ResponseInterface
    {
        return $this->renderMessage(
            $this->translator()->translate('voyti.security.social_auth_cancelled'),
        );
    }

    public function handleSuccess(AuthClientInterface $client): ResponseInterface
    {
        $provider = $client->getName();
        $attributes = $this->normalizer->normalize($provider, $client);

        $identity = $this->currentUser->getIdentity();
        $isGuest = $identity instanceof GuestIdentityInterface;

        try {
            $result = $isGuest
                ? $this->socialAuthenticateService->run($provider, $attributes['id'], $attributes, $_SERVER)
                : $this->socialAccountConnectService->run($provider, $attributes['id'], $attributes, (int) $identity->getId());
        } catch (RuntimeException $exception) {
            return $this->renderMessage($exception->getMessage());
        }

        if ($result->isFailure()) {
            return $this->renderMessage($result->getMessage());
        }

        if (!$isGuest) {
            return $this->redirect($this->url->generate('voyti/user-social-network'));
        }

        $account = $this->pendingSocialAccountService->getPendingAccount();
        if ($account !== null) {
            return $this->redirect(
                $this->url->generate('voyti/registration-connect', ['code' => $account->getCode() ?? 'connect']),
            );
        }

        $user = $this->currentUser->getIdentity();
        if ($user instanceof User) {
            return $this->rememberMeCookieService->addCookie(
                $user,
                $this->redirect($this->homeUrl()),
                $this->session->getId() ?? '',
            );
        }

        return $this->renderMessage($this->translator()->translate('voyti.security.authenticated'));
    }

    private function renderMessage(string $title): ResponseInterface
    {
        return $this->renderView('shared/message', [
            'data' => new MessageViewData(title: $title, homeUrl: $this->homeUrl()),
        ]);
    }
}
