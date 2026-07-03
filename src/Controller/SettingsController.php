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
use YiiRocks\Voyti\Event\Gdpr\GdprEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Form\Settings\GdprConsentForm;
use YiiRocks\Voyti\Form\Settings\GdprDeleteForm;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory;
use Yiisoft\Http\Method;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class SettingsController
{
    use InputDataTrait;
    use RenderTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly WebViewRenderer $viewRenderer,
        private readonly UserRepository $userRepository,
        private readonly UserProfileRepository $userProfileRepository,
        private readonly UserSocialAccountRepository $userSocialAccountRepository,
        private readonly PasswordHasher $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UrlGeneratorInterface $url,
        private readonly ModuleConfig $config,
        private readonly AuthClientRegistry $authClientRegistry,
        private readonly EmailChangeStrategyFactory $emailChangeStrategyFactory,
        private readonly QrCodeUriGeneratorService $twoFactorQrCodeService,
        private readonly EmailChangeService $emailChangeService,
        private readonly UserTokenRepository $userTokenRepository,
        private readonly HydratorInterface $hydrator,
        private readonly CurrentUser $currentUser,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function account(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderError('voyti.settings.not_authenticated');
        }

        $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->renderError('voyti.settings.user_not_found');
        }

        $form = new SettingsForm($this->translator);
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
                        $this->config->emailChangeStrategy,
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

                return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.account_details_updated', category: 'voyti')]);
            }
        }

        return $this->renderView('settings/account', ['model' => $form, 'config' => $this->config]);
    }

    public function confirm(ServerRequestInterface $request, string $code): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found', category: 'voyti')]);
        }

        if ($this->emailChangeService->run($code, $user)) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.email_changed', category: 'voyti')]);
        }

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.email_change_failed', category: 'voyti'), 'translator' => $this->translator]);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->allowAccountDelete) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.account_deletion_disabled', category: 'voyti'), 'translator' => $this->translator]);
        }

        $identity = $this->currentUser->getIdentity();
        if (!($identity instanceof GuestIdentityInterface)) {
            $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
            if ($user !== null) {
                $this->eventDispatcher->dispatch(new UserEvent($user));
                $this->userRepository->delete($user);
                $this->eventDispatcher->dispatch(new UserEvent($user));
            }
        }

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.account_deleted', category: 'voyti')]);
    }

    public function disconnect(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
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
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.network_disconnected', category: 'voyti'), 'translator' => $this->translator]);
        }

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.network_not_found', category: 'voyti'), 'translator' => $this->translator]);
    }

    public function export(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableGdprCompliance) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available', category: 'voyti'), 'translator' => $this->translator]);
        }

        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found', category: 'voyti'), 'translator' => $this->translator]);
        }

        $data = [];
        foreach ($this->config->gdprExportProperties as $property) {
            $value = $this->exportValue($user, $property);
            if ($value !== null) {
                $data[$property] = $value;
            }
        }

        $csv = implode(',', array_keys($data)) . "\n" . implode(',', array_map(
            static fn (mixed $v): string => '"' . str_replace('"', '""', (string) $v) . '"',
            array_values($data),
        ));

        $response = $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.data_exported', category: 'voyti'), 'translator' => $this->translator]);
        return $response;
    }

    public function gdprConsent(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableGdprCompliance) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available', category: 'voyti'), 'translator' => $this->translator]);
        }

        $form = new GdprConsentForm($this->translator);
        $identity = $this->currentUser->getIdentity();
        if (!($identity instanceof GuestIdentityInterface) && $request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
            if ($user !== null) {
                $user->setGdprConsent($form->consent);
                $user->setGdprConsentDate($form->consent ? time() : null);
                $user->save();
                return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.gdpr_consent_saved', category: 'voyti')]);
            }
        }

        if (!($identity instanceof GuestIdentityInterface)) {
            $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
            if ($user !== null) {
                $form->consent = $user->isGdprConsent();
            }
        }

        return $this->renderView('settings/gdpr-consent', ['model' => $form]);
    }

    public function gdprDelete(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableGdprCompliance) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available', category: 'voyti'), 'translator' => $this->translator]);
        }

        $form = new GdprDeleteForm($this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));

            $identity = $this->currentUser->getIdentity();
            if (!($identity instanceof GuestIdentityInterface)) {
                $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
                if ($user !== null &&         $this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                    $this->eventDispatcher->dispatch(new GdprEvent($user));
                    $prefix = $this->config->gdprAnonymizePrefix . ($user->getId() ?? '');
                    $user->setEmail($prefix . '@example.com');
                    $user->setUsername($prefix);
                    $user->setGdprDeleted(true);
                    $user->setBlockedAt(time());
                    $user->setAuthKey(Random::string());
                    $user->save();
                    $this->eventDispatcher->dispatch(new GdprEvent($user));
                    return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.personal_info_removed', category: 'voyti')]);
                }
            }
        }

        return $this->renderView('settings/gdpr-delete', ['model' => $form]);
    }

    public function networks(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $accounts = $this->userSocialAccountRepository->findByUserId((int) ($identity->getId() ?? 0));
        /**
         * @infection-ignore-all
         *
         * array_values() only re-indexes to satisfy the list<string> type; the sole
         * consumer (_connect.php's in_array() check) never depends on key order.
         */
        $connectedProviders = array_values(array_filter(array_map(
            static fn (\YiiRocks\Voyti\Entity\UserSocialAccount $account): string => $account->getProvider(),
            $accounts,
        )));

        return $this->renderView('settings/networks', [
            'accounts' => $accounts,
            'config' => $this->config,
            'authClients' => $this->authClientRegistry,
            'connectRouteName' => 'voyti/connect',
            'excludedProviders' => $connectedProviders,
        ]);
    }

    public function privacy(): ResponseInterface
    {
        if (!$this->config->enableGdprCompliance) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available', category: 'voyti'), 'translator' => $this->translator]);
        }
        return $this->renderView('settings/privacy', ['config' => $this->config]);
    }

    public function twoFactor(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableTwoFactorAuthentication) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available', category: 'voyti'), 'translator' => $this->translator]);
        }

        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found', category: 'voyti'), 'translator' => $this->translator]);
        }

        $qrCodeSvg = $this->twoFactorQrCodeService->generateQrCodeSvg($user);

        return $this->renderView('settings/two-factor', [
            'user' => $user,
            'qrCodeUri' => $qrCodeSvg,
            'secret' => $user->getAuthTfKey(),
            'config' => $this->config,
            'errors' => [],
        ]);
    }

    public function twoFactorDisable(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableTwoFactorAuthentication) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available', category: 'voyti'), 'translator' => $this->translator]);
        }

        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user->setAuthTfEnabled(false);
        $user->setAuthTfKey(null);
        $user->setAuthTfType(null);
        $user->save();

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.two_factor_disabled', category: 'voyti'), 'translator' => $this->translator]);
    }

    public function twoFactorEnable(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableTwoFactorAuthentication) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available', category: 'voyti'), 'translator' => $this->translator]);
        }

        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user->setAuthTfEnabled(true);
        $user->setAuthTfType('google');
        $user->save();

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.two_factor_enabled', category: 'voyti'), 'translator' => $this->translator]);
    }

    public function userProfile(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        if ($identity instanceof GuestIdentityInterface) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById((int) ($identity->getId() ?? 0));
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found', category: 'voyti')]);
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
            return $this->redirect($this->url->generate('voyti/settings'));
        }

        return $this->renderView('settings/profile', [
            'model' => $form,
            'user' => $user,
            'userProfile' => $userProfile,
            'errors' => [],
            'config' => $this->config,
        ]);
    }

    protected function viewPath(): string
    {
        return $this->config->viewPath;
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
            default => null,
        };
    }

    private function redirect(string $url): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse(302)
            ->withHeader('Location', $url);
    }
}
