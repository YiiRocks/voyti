<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Entity\Profile;
use YiiRocks\Voyti\Event\Gdpr\GdprEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Form\Settings\GdprDeleteForm;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\ProfileRepository;
use YiiRocks\Voyti\Repository\SocialNetworkAccountRepository;
use YiiRocks\Voyti\Repository\TokenRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Http\Method;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class SettingsController
{
    use RenderTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly WebViewRenderer $viewRenderer,
        private readonly UserRepository $userRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly SocialNetworkAccountRepository $socialNetworkAccountRepository,
        private readonly SecurityHelper $securityHelper,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UrlGeneratorInterface $url,
        private readonly ModuleConfig $config,
        private readonly EmailChangeStrategyFactory $emailChangeStrategyFactory,
        private readonly QrCodeUriGeneratorService $twoFactorQrCodeService,
        private readonly EmailChangeService $emailChangeService,
        private readonly TokenRepository $tokenRepository,
    ) {
    }

    public function account(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->renderError('voyti.settings.not_authenticated');
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->renderError('voyti.settings.user_not_found');
        }

        $form = new SettingsForm($this->translator);
        $form->username = $user->getUsername();
        $form->email = $user->getEmail();

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $form->load($body, 'settings');
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
                    $user->setPasswordHash($this->securityHelper->hashPassword($form->password, $this->config->blowfishCost));
                    $user->setPasswordChangedAt(time());
                }

                $user->setUpdatedAt(time());
                $user->save();

                return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.account_details_updated', category: 'voyti'), 'translator' => $this->translator]);
            }
        }

        return $this->renderView('settings/account', ['model' => $form]);
    }

    public function confirm(ServerRequestInterface $request, string $code): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found', category: 'voyti'), 'translator' => $this->translator]);
        }

        if ($this->emailChangeService->run($code, $user)) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.email_changed', category: 'voyti'), 'translator' => $this->translator]);
        }

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.email_change_failed', category: 'voyti'), 'translator' => $this->translator]);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->allowAccountDelete) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.account_deletion_disabled', category: 'voyti'), 'translator' => $this->translator]);
        }

        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity !== null) {
            $user = $this->userRepository->findById($identity->getId() ?? 0);
            if ($user !== null) {
                $this->eventDispatcher->dispatch(new UserEvent($user));
                $this->userRepository->delete($user);
                $this->eventDispatcher->dispatch(new UserEvent($user));
            }
        }

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.account_deleted', category: 'voyti'), 'translator' => $this->translator]);
    }

    public function disconnect(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $account = $this->socialNetworkAccountRepository->findByProviderAndClientId('', '');
        if ($account === null) {
            $accounts = $this->socialNetworkAccountRepository->findByUserId((int) ($identity->getId() ?? 0));
            foreach ($accounts as $a) {
                if ($a->getId() === $id) {
                    $account = $a;
                    break;
                }
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

        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found', category: 'voyti'), 'translator' => $this->translator]);
        }

        $data = [];
        foreach ($this->config->gdprExportProperties as $property) {
            if (str_contains($property, '.')) {
                [$relation, $field] = explode('.', $property, 2);
                if ($relation === 'profile') {
                    $profile = $user->getProfile();
                    if ($profile !== null) {
                        $data[$property] = $profile->getPropertyValue($field);
                    }
                }
            } else {
                $data[$property] = $user->getPropertyValue($property);
            }
        }

        $csv = implode(',', array_keys($data)) . "\n" . implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', array_values($data)));

        $response = $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.data_exported', category: 'voyti'), 'translator' => $this->translator]);
        return $response;
    }

    public function gdprConsent(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableGdprCompliance) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available', category: 'voyti'), 'translator' => $this->translator]);
        }

        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity !== null && $request->getMethod() === Method::POST) {
            $user = $this->userRepository->findById($identity->getId() ?? 0);
            if ($user !== null) {
                $user->setGdprConsent(true);
                $user->setGdprConsentDate(time());
                $user->save();
                return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.gdpr_consent_saved', category: 'voyti'), 'translator' => $this->translator]);
            }
        }

        return $this->renderView('settings/gdpr-consent', ['config' => $this->config]);
    }

    public function gdprDelete(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableGdprCompliance) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available', category: 'voyti'), 'translator' => $this->translator]);
        }

        $form = new GdprDeleteForm($this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $form->load($body, 'gdpr-delete');

            $identity = $request->getAttribute(IdentityInterface::class);
            if ($identity !== null) {
                $user = $this->userRepository->findById($identity->getId() ?? 0);
                if ($user !== null && $this->securityHelper->validatePassword($form->password, $user->getPasswordHash())) {
                    $this->eventDispatcher->dispatch(new GdprEvent($user));
                    $prefix = $this->config->gdprAnonymizePrefix . $user->getId();
                    $user->setEmail($prefix . '@example.com');
                    $user->setUsername($prefix);
                    $user->setGdprDeleted(true);
                    $user->setBlockedAt(time());
                    $user->setAuthKey($this->securityHelper->generateRandomString());
                    $user->save();
                    $this->eventDispatcher->dispatch(new GdprEvent($user));
                    return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.personal_info_removed', category: 'voyti'), 'translator' => $this->translator]);
                }
            }
        }

        return $this->renderView('settings/gdpr-delete', ['model' => $form]);
    }

    public function networks(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }
        return $this->renderView('settings/networks', ['user' => $identity]);
    }

    public function privacy(): ResponseInterface
    {
        if (!$this->config->enableGdprCompliance) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available', category: 'voyti'), 'translator' => $this->translator]);
        }
        return $this->renderView('settings/privacy', ['config' => $this->config]);
    }

    public function profile(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found', category: 'voyti'), 'translator' => $this->translator]);
        }

        $profile = $user->getProfile();
        if ($profile === null) {
            $profile = new Profile();
            $profile->setUserId($user->getId() ?? 0);
        }

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $profile->load($body, 'profile');
            if ($profile->save()) {
                return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.profile_updated', category: 'voyti'), 'translator' => $this->translator]);
            }
        }

        return $this->renderView('settings/profile', ['model' => $profile, 'config' => $this->config]);
    }

    public function twoFactor(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableTwoFactorAuthentication) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available', category: 'voyti'), 'translator' => $this->translator]);
        }

        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found', category: 'voyti'), 'translator' => $this->translator]);
        }

        $qrCodeSvg = $this->twoFactorQrCodeService->generateQrCodeSvg($user);

        return $this->renderView('settings/two-factor', [
            'user' => $user,
            'qrCodeUri' => $qrCodeSvg,
            'config' => $this->config,
            'errors' => [],
        ]);
    }

    public function twoFactorDisable(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableTwoFactorAuthentication) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available', category: 'voyti'), 'translator' => $this->translator]);
        }

        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
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

        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found', category: 'voyti'), 'translator' => $this->translator]);
        }

        $user->setAuthTfEnabled(true);
        $user->setAuthTfType('google');
        $user->save();

        return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.two_factor_enabled', category: 'voyti'), 'translator' => $this->translator]);
    }
}
