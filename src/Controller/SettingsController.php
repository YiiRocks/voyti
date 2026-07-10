<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Entity\UserSessionHistory;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Event\Gdpr\GdprEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Event\User\UserProfileEvent;
use YiiRocks\Voyti\Form\Settings\AnonymizeForm;
use YiiRocks\Voyti\Form\Settings\DeleteAccountForm;
use YiiRocks\Voyti\Form\Settings\GdprConsentForm;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserSessionHistoryRepository;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\Service\UserSessionHistory\TerminateUserSessionsService;
use YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory;
use YiiRocks\Voyti\Validator\TwoFactor\CodeValidator;
use YiiRocks\Voyti\Validator\TwoFactor\EmailValidator;
use Yiisoft\Http\Header;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Json\Json;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class SettingsController
{
    use InputDataTrait;
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private UserSessionHistoryRepository $userSessionHistoryRepository,
        private UserSocialAccountRepository $userSocialAccountRepository,
        private PasswordHasher $passwordHasher,
        private ValidatorInterface $validator,
        private EventDispatcherInterface $eventDispatcher,
        private UrlGeneratorInterface $url,
        private ModuleConfig $config,
        private AuthClientRegistry $authClientRegistry,
        private EmailChangeStrategyFactory $emailChangeStrategyFactory,
        private QrCodeUriGeneratorService $twoFactorQrCodeService,
        private EmailCodeGeneratorService $twoFactorEmailCodeService,
        private EmailChangeService $emailChangeService,
        private UserTokenRepository $userTokenRepository,
        private HydratorInterface $hydrator,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private TerminateUserSessionsService $terminateUserSessionsService,
        private FlashInterface $flash,
        private SwitchIdentityService $switchIdentityService,
    ) {
    }

    public function account(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        $form = new SettingsForm($this->config, $this->translator);
        $form->username = $user->getUsername();
        $form->email = $user->getEmail();

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $user->setUsername($form->username);

                if ($form->email !== $user->getEmail()) {
                    $form->setUser($user);
                    $strategy = $this->emailChangeStrategyFactory->makeByStrategyType(
                        $this->config->emailChangeConfirmation,
                        $form,
                    );
                    $strategy->run();
                }

                if ($form->password !== '') {
                    $user->setPasswordHash($this->passwordHasher->hash($form->password));
                    $user->setPasswordChangedAt(time());
                }

                $user->setUpdatedAt(time());
                $user->save();

                return $this->redirectWithFlash(
                    $this->url->generate('voyti/settings-account'),
                    'voyti.settings.account_details_updated',
                );
            }
        }

        return $this->renderView('settings/account', ['model' => $form, 'config' => $this->config, 'flash' => $this->flash]);
    }

    public function anonymize(ServerRequestInterface $request): ResponseInterface
    {
        $form = new AnonymizeForm($this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $result = $this->validator->validate($form);

            $identity = $this->currentUser->getIdentity();
            if ($result->isValid() && !($identity instanceof GuestIdentityInterface)) {
                $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
                if ($user !== null &&         $this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                    $this->eventDispatcher->dispatch(new GdprEvent($user));
                    $prefix = $this->config->gdprAnonymizePrefix . ($user->getId() ?? '');
                    $user->setEmail($prefix . '@example.com');
                    $user->setUsername($prefix);
                    $user->setAnonymized(true);
                    $user->setBlockedAt(time());
                    $user->setAuthKey(Random::string());
                    $user->save();
                    $this->eventDispatcher->dispatch(new GdprEvent($user));
                    $this->terminateUserSessionsService->run($user->getIdOrZero());
                    return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.personal_info_removed', category: 'voyti')]);
                }
            }
        }

        return $this->renderView('settings/privacy/anonymize', ['model' => $form]);
    }

    public function confirm(ServerRequestInterface $request, string $code): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if ($this->emailChangeService->run($code, $user)) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.email_changed', category: 'voyti')]);
        }

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.email_change_failed', category: 'voyti'), 'translator' => $this->translator]);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $form = new DeleteAccountForm($this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $result = $this->validator->validate($form);

            $identity = $this->currentUser->getIdentity();
            if ($result->isValid() && !($identity instanceof GuestIdentityInterface)) {
                $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
                if ($user !== null && $this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                    $userId = $user->getIdOrZero();
                    $this->eventDispatcher->dispatch(new UserEvent($user));
                    $this->userRepository->delete($user);
                    $this->eventDispatcher->dispatch(new UserEvent($user));
                    $this->terminateUserSessionsService->run($userId);
                    return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.account_deleted', category: 'voyti')]);
                }
            }
        }

        return $this->renderView('settings/privacy/delete', ['model' => $form]);
    }

    public function disconnect(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti')]);
        }

        $account = null;
        $accounts = $this->userSocialAccountRepository->findByUserId((int) ($identity->getId() ?? 0));
        foreach ($accounts as $candidate) {
            if ($candidate->getId() === $id) {
                $account = $candidate;
                break;
            }
        }

        if ($account !== null) {
            $account->delete();
            return $this->redirectWithFlash(
                $this->url->generate('voyti/settings-networks'),
                'voyti.settings.network_disconnected',
            );
        }

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.network_not_found', category: 'voyti')]);
    }

    public function export(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        $values = array_map(
            fn (string $property): mixed => $this->exportValue($user, $property),
            $this->config->gdprExportProperties,
        );
        /** @var array<array-key, mixed> $data */
        $data = array_filter(
            array_combine($this->config->gdprExportProperties, $values),
            static fn (mixed $v): bool => $v !== null,
        );

        $json = Json::encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $response = $this->responseFactory->createResponse(Status::OK)
            ->withHeader(Header::CONTENT_TYPE, 'application/json; charset=UTF-8')
            ->withHeader(Header::CONTENT_DISPOSITION, 'attachment; filename="user-data-export.json"');
        $response->getBody()->write($json);

        return $response;
    }

    public function gdprConsent(ServerRequestInterface $request): ResponseInterface
    {
        $form = new GdprConsentForm($this->translator);
        $identity = $this->currentUser->getIdentity();
        if (!($identity instanceof GuestIdentityInterface) && $request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
            if ($user !== null) {
                if ($form->consent && !$user->isGdprConsent()) {
                    $user->setGdprConsent(true);
                    $user->setGdprConsentDate(time());
                    $user->save();
                }
                return $this->redirectWithFlash(
                    $this->url->generate('voyti/settings-privacy-gdpr-consent'),
                    'voyti.settings.gdpr_consent_saved',
                );
            }
        }

        if (!($identity instanceof GuestIdentityInterface)) {
            $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
            if ($user !== null) {
                $form->consent = $user->isGdprConsent();
                $form->consentDate = $user->getGdprConsentDate();
                $form->timezone = $user->getProfile()?->getTimezone();
            }
        }

        return $this->renderView('settings/privacy/gdpr-consent', ['model' => $form, 'flash' => $this->flash]);
    }

    public function networks(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $accounts = $this->userSocialAccountRepository->findByUserId((int) ($identity->getId() ?? 0));
        $connectedProviders = array_filter(array_map(
            static fn (\YiiRocks\Voyti\Entity\UserSocialAccount $account): string => $account->getProvider(),
            $accounts,
        ));

        return $this->renderView('settings/networks', [
            'accounts' => $accounts,
            'config' => $this->config,
            'authClients' => $this->authClientRegistry,
            'connectRouteName' => 'voyti/connect',
            'excludedProviders' => $connectedProviders,
            'flash' => $this->flash,
        ]);
    }

    public function privacy(): ResponseInterface
    {
        return $this->renderView('settings/privacy', ['config' => $this->config]);
    }

    public function twoFactor(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if (!$user->isAuthTfEnabled()) {
            return $this->renderView('settings/two-factor', [
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

        return $this->renderView('settings/two-factor', [
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

    public function twoFactorDisable(ServerRequestInterface $request): ResponseInterface
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
            return $this->renderView('settings/two-factor', [
                'user' => $user,
                'method' => $method,
                'qrCodeUri' => '',
                'secret' => null,
                'emailCodeSent' => $method === 'email',
                'config' => $this->config,
                'errors' => ['code' => [$this->twoFactorErrorMessage($errorMessage)]],
                'flash' => $this->flash,
                'preloadContent' => true,
            ]);
        }

        $user->setAuthTfEnabled(false);
        $user->setAuthTfKey(null);
        $user->setAuthTfType(null);
        $user->save();

        return $this->redirectWithFlash(
            $this->url->generate('voyti/settings-two-factor'),
            'voyti.settings.two_factor_disabled',
        );
    }

    public function twoFactorDisableSendCode(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if (!$user->isAuthTfEnabled() || $user->getAuthTfType() !== 'email') {
            return $this->redirect($this->url->generate('voyti/settings-two-factor'));
        }

        $this->twoFactorEmailCodeService->run($user);

        return $this->renderView('settings/two-factor', [
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

    public function twoFactorEmail(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if ($user->isAuthTfEnabled()) {
            return $this->redirect($this->url->generate('voyti/settings-two-factor'));
        }

        return $this->renderTwoFactorSetup($request, 'settings/two-factor/_email', [
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

    public function twoFactorEnable(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if ($user->isAuthTfEnabled()) {
            return $this->redirectWithFlash(
                $this->url->generate('voyti/settings-two-factor'),
                'voyti.settings.two_factor_enabled',
            );
        }

        $body = $this->parsedBody($request);
        $method = $this->stringValue($body, 'method', 'google') === 'email' ? 'email' : 'google';
        $code = $this->stringValue($body, 'code');

        if ($method === 'email') {
            $emailValidator = new EmailValidator($user, $code);
            if (!$emailValidator->validate()) {
                return $this->renderView('settings/two-factor', [
                    'user' => $user,
                    'method' => 'email',
                    'qrCodeUri' => '',
                    'secret' => null,
                    'emailCodeSent' => true,
                    'config' => $this->config,
                    'errors' => ['code' => [$this->twoFactorErrorMessage($emailValidator->getErrorMessage())]],
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

                return $this->renderView('settings/two-factor', [
                    'user' => $user,
                    'method' => 'google',
                    'qrCodeUri' => $this->twoFactorQrCodeService->generateQrCodeSvg($user),
                    'secret' => $user->getAuthTfKey(),
                    'emailCodeSent' => false,
                    'config' => $this->config,
                    'errors' => ['code' => [$this->twoFactorErrorMessage($codeValidator->getErrorMessage())]],
                    'flash' => $this->flash,
                    'preloadContent' => true,
                ]);
            }

            $user->setAuthTfType('google');
        }

        $user->setAuthTfEnabled(true);
        $user->save();

        return $this->redirectWithFlash(
            $this->url->generate('voyti/settings-two-factor'),
            'voyti.settings.two_factor_enabled',
        );
    }

    public function twoFactorGoogle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if ($user->isAuthTfEnabled()) {
            return $this->redirect($this->url->generate('voyti/settings-two-factor'));
        }

        return $this->renderGoogleSetup($request, $user);
    }

    public function twoFactorRenew(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->jsonErrorResponse(Status::UNAUTHORIZED, 'voyti.settings.not_authenticated');
        }

        $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
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

    public function twoFactorSendEmailCode(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if ($user->isAuthTfEnabled()) {
            return $this->redirectWithFlash(
                $this->url->generate('voyti/settings-two-factor'),
                'voyti.settings.two_factor_enabled',
            );
        }

        if ($user->getAuthTfType() !== 'email') {
            $user->setAuthTfType('email');
            $user->save();
        }

        $this->twoFactorEmailCodeService->run($user);

        return $this->renderView('settings/two-factor', [
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

    public function userProfile(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        $userProfile = $user->getProfile();
        if ($userProfile === null) {
            $userProfile = new UserProfile();
            $userProfile->setUserId((int) $user->getId());
        }

        $form = new UserProfileForm($this->translator);
        $form->name = $userProfile->getName() ?? '';
        $form->publicEmail = $userProfile->getPublicEmail() ?? '';
        $form->gravatarEmail = $userProfile->getGravatarEmail() ?? '';
        $form->location = $userProfile->getLocation() ?? '';
        $form->website = $userProfile->getWebsite() ?? '';
        $form->timezone = $userProfile->getTimezone() ?? '';
        $form->bio = $userProfile->getBio() ?? '';

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));

            $userProfile->setName($form->name);
            $userProfile->setPublicEmail($form->publicEmail);
            $userProfile->setGravatarEmail($form->gravatarEmail);
            $userProfile->setLocation($form->location);
            $userProfile->setWebsite($form->website);
            $userProfile->setTimezone($form->timezone);
            $userProfile->setBio($form->bio);

            $userProfile->save();
            $this->eventDispatcher->dispatch(new UserProfileEvent($userProfile));
            return $this->redirectWithFlash($this->url->generate('voyti/settings'), 'voyti.settings.profile_updated');
        }

        return $this->renderView('settings/profile', [
            'model' => $form,
            'user' => $user,
            'userProfile' => $userProfile,
            'errors' => [],
            'config' => $this->config,
            'flash' => $this->flash,
            'isSwitched' => $this->switchIdentityService->isSwitched(),
            'originalUser' => $this->switchIdentityService->getOriginalUser(),
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

    private function exportValue(User $user, string $property): mixed
    {
        return match ($property) {
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'userProfile.public_email' => $user->getProfile()?->getPublicEmail(),
            'userProfile.name' => $user->getProfile()?->getName(),
            'userProfile.gravatar_email' => $user->getProfile()?->getGravatarEmail(),
            'userProfile.location' => $user->getProfile()?->getLocation(),
            'userProfile.website' => $user->getProfile()?->getWebsite(),
            'userProfile.bio' => $user->getProfile()?->getBio(),
            'userSessionHistory' => array_map(
                static fn (UserSessionHistory $entry): array => [
                    'ip' => $entry->getIp(),
                    'user_agent' => $entry->getUserAgent(),
                    'created_at' => $entry->getCreatedAt(),
                    'updated_at' => $entry->getUpdatedAt(),
                ],
                $this->userSessionHistoryRepository->findByUserId($user->getIdOrZero()),
            ),
            'userSocialAccount' => array_map(
                static fn (UserSocialAccount $account): array => [
                    'provider' => $account->getProvider(),
                    'username' => $account->getUsername(),
                    'email' => $account->getEmail(),
                    'created_at' => $account->getCreatedAt(),
                    'data' => $account->getDecodedData(),
                ],
                $this->userSocialAccountRepository->findByUserId($user->getIdOrZero()),
            ),
            default => null,
        };
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

        return $this->renderTwoFactorSetup($request, 'settings/two-factor/_google', [
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

        return $this->renderView('settings/two-factor', $params + ['preloadContent' => true]);
    }

    private function requireUser(): User|ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderError('voyti.settings.not_authenticated');
        }

        $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->renderError('voyti.settings.user_not_found');
        }

        return $user;
    }

    private function twoFactorErrorMessage(string $validatorMessage): string
    {
        return $validatorMessage !== ''
            ? $validatorMessage
            : $this->translator->translate('voyti.validator.invalid_verification_code', category: 'voyti');
    }
}
