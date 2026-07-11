<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\TwoFactor;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\Validator\TwoFactor\CodeValidator;
use YiiRocks\Voyti\Validator\TwoFactor\EmailValidator;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Json\Json;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class TwoFactorController
{
    use InputDataTrait;
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private ModuleConfig $config,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private QrCodeUriGeneratorService $twoFactorQrCodeService,
        private EmailCodeGeneratorService $twoFactorEmailCodeService,
        private FlashInterface $flash,
    ) {
    }

    public function disable(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        $body = $this->parsedBody($request);
        $code = $this->stringValue($body, 'code');
        $method = $user->getAuthTfType() ?? 'google';

        if ($method === 'email') {
            $emailValidator = new EmailValidator($user, $code);
            $isValid = $emailValidator->validate();
            $errorMessage = $emailValidator->getErrorMessage();
        } else {
            $codeValidator = new CodeValidator($user, $code);
            $codeValidator->setTranslator($this->translator);
            $isValid = $codeValidator->validate();
            $errorMessage = $codeValidator->getErrorMessage();
        }

        if (!$isValid) {
            return $this->renderView('two-factor/index', [
                'user' => $user,
                'method' => $method,
                'qrCodeUri' => '',
                'secret' => null,
                'emailCodeSent' => $method === 'email',
                'config' => $this->config,
                'errors' => ['code' => [$this->errorMessage($errorMessage)]],
                'flash' => $this->flash,
                'preloadContent' => true,
            ]);
        }

        $user->setAuthTfEnabled(false);
        $user->setAuthTfKey(null);
        $user->setAuthTfType(null);
        $user->save();

        return $this->redirectWithFlash(
            $this->url->generate('voyti/two-factor'),
            'voyti.settings.two_factor_disabled',
        );
    }

    public function disableSendCode(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if (!$user->isAuthTfEnabled() || $user->getAuthTfType() !== 'email') {
            return $this->redirect($this->url->generate('voyti/two-factor'));
        }

        $this->twoFactorEmailCodeService->run($user);

        return $this->renderView('two-factor/index', [
            'user' => $user,
            'method' => 'email',
            'qrCodeUri' => '',
            'secret' => null,
            'emailCodeSent' => true,
            'config' => $this->config,
            'errors' => [],
            'flash' => $this->flash,
            'preloadContent' => true,
        ]);
    }

    public function email(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if ($user->isAuthTfEnabled()) {
            return $this->redirect($this->url->generate('voyti/two-factor'));
        }

        return $this->renderTwoFactorSetup($request, 'two-factor/_email', [
            'user' => $user,
            'method' => 'email',
            'qrCodeUri' => '',
            'secret' => null,
            'emailCodeSent' => false,
            'config' => $this->config,
            'errors' => [],
            'flash' => $this->flash,
        ]);
    }

    public function enable(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if ($user->isAuthTfEnabled()) {
            return $this->redirectWithFlash(
                $this->url->generate('voyti/two-factor'),
                'voyti.settings.two_factor_enabled',
            );
        }

        $body = $this->parsedBody($request);
        $method = $this->stringValue($body, 'method', 'google') === 'email' ? 'email' : 'google';
        $code = $this->stringValue($body, 'code');

        if ($method === 'email') {
            $emailValidator = new EmailValidator($user, $code);
            if (!$emailValidator->validate()) {
                return $this->renderView('two-factor/index', [
                    'user' => $user,
                    'method' => 'email',
                    'qrCodeUri' => '',
                    'secret' => null,
                    'emailCodeSent' => true,
                    'config' => $this->config,
                    'errors' => ['code' => [$this->errorMessage($emailValidator->getErrorMessage())]],
                    'flash' => $this->flash,
                    'preloadContent' => true,
                ]);
            }

            $user->setAuthTfType('email');
        } else {
            $codeValidator = new CodeValidator($user, $code);
            $codeValidator->setTranslator($this->translator);
            if (!$codeValidator->validate()) {
                $this->ensureFreshGoogleAuthenticatorSecret($user);

                return $this->renderView('two-factor/index', [
                    'user' => $user,
                    'method' => 'google',
                    'qrCodeUri' => $this->twoFactorQrCodeService->generateQrCodeSvg($user),
                    'secret' => $user->getAuthTfKey(),
                    'emailCodeSent' => false,
                    'config' => $this->config,
                    'errors' => ['code' => [$this->errorMessage($codeValidator->getErrorMessage())]],
                    'flash' => $this->flash,
                    'preloadContent' => true,
                ]);
            }

            $user->setAuthTfType('google');
        }

        $user->setAuthTfEnabled(true);
        $user->save();

        return $this->redirectWithFlash(
            $this->url->generate('voyti/two-factor'),
            'voyti.settings.two_factor_enabled',
        );
    }

    public function google(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if ($user->isAuthTfEnabled()) {
            return $this->redirect($this->url->generate('voyti/two-factor'));
        }

        return $this->renderGoogleSetup($request, $user);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if (!$user->isAuthTfEnabled()) {
            return $this->renderView('two-factor/index', [
                'user' => $user,
                'method' => 'google',
                'qrCodeUri' => '',
                'secret' => null,
                'emailCodeSent' => false,
                'config' => $this->config,
                'errors' => [],
                'flash' => $this->flash,
                'preloadContent' => false,
            ]);
        }

        return $this->renderView('two-factor/index', [
            'user' => $user,
            'method' => $user->getAuthTfType() ?? 'google',
            'qrCodeUri' => '',
            'secret' => null,
            'emailCodeSent' => false,
            'config' => $this->config,
            'errors' => [],
            'flash' => $this->flash,
            'preloadContent' => true,
        ]);
    }

    public function renew(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->jsonErrorResponse(Status::UNAUTHORIZED, 'voyti.settings.not_authenticated');
        }

        $user = User::findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->jsonErrorResponse(Status::NOT_FOUND, 'voyti.settings.user_not_found');
        }

        if ($user->isAuthTfEnabled()) {
            return $this->jsonErrorResponse(Status::FORBIDDEN, 'voyti.view.two_factor.already_enabled');
        }

        if (!$this->twoFactorQrCodeService->isAvailable()) {
            return $this->jsonErrorResponse(Status::SERVICE_UNAVAILABLE, 'voyti.validator.two_factor_library_missing');
        }

        if ($user->getAuthTfType() !== 'google') {
            $user->setAuthTfType('google');
        }
        $qrCodeSvg = $this->twoFactorQrCodeService->generateQrCodeSvg($user, forceNewSecret: true);

        $response = $this->responseFactory->createResponse(Status::OK)
            ->withHeader(Header::CONTENT_TYPE, 'application/json; charset=UTF-8');
        $response->getBody()->write(Json::encode([
            'qrCodeUri' => $qrCodeSvg,
            'secret' => $user->getAuthTfKey(),
        ]));

        return $response;
    }

    public function sendEmailCode(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if ($user->isAuthTfEnabled()) {
            return $this->redirectWithFlash(
                $this->url->generate('voyti/two-factor'),
                'voyti.settings.two_factor_enabled',
            );
        }

        if ($user->getAuthTfType() !== 'email') {
            $user->setAuthTfType('email');
            $user->save();
        }

        $this->twoFactorEmailCodeService->run($user);

        return $this->renderView('two-factor/index', [
            'user' => $user,
            'method' => 'email',
            'qrCodeUri' => '',
            'secret' => null,
            'emailCodeSent' => true,
            'config' => $this->config,
            'errors' => [],
            'flash' => $this->flash,
            'preloadContent' => true,
        ]);
    }

    protected function viewPath(): string
    {
        return $this->config->viewPath;
    }

    /**
     * The TOTP secret and the email one-time code share the same auth_tf_key column.
     * If an email code was last sent to the user, that column holds a 6-digit code
     * rather than a TOTP secret, so it must be cleared before a QR code is generated -
     * otherwise QrCodeUriGeneratorService::run() would treat the leftover email code
     * as a real TOTP secret and reuse it verbatim.
     */
    private function ensureFreshGoogleAuthenticatorSecret(User $user): void
    {
        if ($user->getAuthTfType() !== 'google') {
            $user->setAuthTfType('google');
            $user->setAuthTfKey(null);
            $user->save();
        }
    }

    private function errorMessage(string $validatorMessage): string
    {
        return $validatorMessage !== ''
            ? $validatorMessage
            : $this->translator->translate('voyti.validator.invalid_verification_code', category: 'voyti');
    }

    private function jsonErrorResponse(int $status, string $messageKey): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status)
            ->withHeader(Header::CONTENT_TYPE, 'application/json; charset=UTF-8');
        $response->getBody()->write(Json::encode([
            'error' => $this->translator->translate($messageKey, category: 'voyti'),
        ]));

        return $response;
    }

    private function renderGoogleSetup(ServerRequestInterface $request, User $user): ResponseInterface
    {
        $this->ensureFreshGoogleAuthenticatorSecret($user);
        $qrCodeSvg = $this->twoFactorQrCodeService->generateQrCodeSvg($user);

        return $this->renderTwoFactorSetup($request, 'two-factor/_google', [
            'user' => $user,
            'method' => 'google',
            'qrCodeUri' => $qrCodeSvg,
            'secret' => $user->getAuthTfKey(),
            'emailCodeSent' => false,
            'config' => $this->config,
            'errors' => [],
            'flash' => $this->flash,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function renderTwoFactorSetup(ServerRequestInterface $request, string $fragmentView, array $params): ResponseInterface
    {
        if (strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest') {
            return $this->renderFragment($fragmentView, $params);
        }

        return $this->renderView('two-factor/index', $params + ['preloadContent' => true]);
    }

    private function requireUser(): User|ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderError('voyti.settings.not_authenticated');
        }

        $user = User::findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->renderError('voyti.settings.user_not_found');
        }

        return $user;
    }
}
