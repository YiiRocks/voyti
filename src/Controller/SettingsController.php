<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Http\Method;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\View\ViewRenderer;
use Yiisoft\Router\UrlGeneratorInterface;
use YiiRocks\Voyti\Form\SettingsForm;
use YiiRocks\Voyti\Form\GdprDeleteForm;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\ProfileRepository;
use YiiRocks\Voyti\Repository\SocialNetworkAccountRepository;
use YiiRocks\Voyti\Event\UserEvent;
use YiiRocks\Voyti\Event\GdprEvent;
use YiiRocks\Voyti\Entity\Profile;
use YiiRocks\Voyti\Strategy\EmailChangeStrategyFactory;
use YiiRocks\Voyti\Service\TwoFactorQrCodeUriGeneratorService;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Repository\TokenRepository;

final class SettingsController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ViewRenderer $viewRenderer,
        private readonly UserRepository $userRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly SocialNetworkAccountRepository $socialNetworkAccountRepository,
        private readonly SecurityHelper $securityHelper,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UrlGeneratorInterface $url,
        private readonly ModuleConfig $config,
        private readonly EmailChangeStrategyFactory $emailChangeStrategyFactory,
        private readonly TwoFactorQrCodeUriGeneratorService $twoFactorQrCodeService,
        private readonly EmailChangeService $emailChangeService,
        private readonly TokenRepository $tokenRepository,
    ) {
    }

    public function profile(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found'), 'translator' => $this->translator]);
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
                return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.profile_updated'), 'translator' => $this->translator]);
            }
        }

        return $this->viewRenderer->render('settings/profile', ['model' => $profile, 'config' => $this->config]);
    }

    public function account(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found'), 'translator' => $this->translator]);
        }

        $form = new SettingsForm();
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

                return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.account_details_updated'), 'translator' => $this->translator]);
            }
        }

        return $this->viewRenderer->render('settings/account', ['model' => $form]);
    }

    public function networks(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated'), 'translator' => $this->translator]);
        }
        return $this->viewRenderer->render('settings/networks', ['user' => $identity]);
    }

    public function privacy(): ResponseInterface
    {
        if (!$this->config->enableGdprCompliance) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available'), 'translator' => $this->translator]);
        }
        return $this->viewRenderer->render('settings/privacy', ['config' => $this->config]);
    }

    public function gdprConsent(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableGdprCompliance) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available'), 'translator' => $this->translator]);
        }

        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity !== null && $request->getMethod() === Method::POST) {
            $user = $this->userRepository->findById($identity->getId() ?? 0);
            if ($user !== null) {
                $user->setGdprConsent(true);
                $user->setGdprConsentDate(time());
                $user->save();
                return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.gdpr_consent_saved'), 'translator' => $this->translator]);
            }
        }

        return $this->viewRenderer->render('settings/gdpr-consent', ['config' => $this->config]);
    }

    public function gdprDelete(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableGdprCompliance) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available'), 'translator' => $this->translator]);
        }

        $form = new GdprDeleteForm();

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
                    return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.personal_info_removed'), 'translator' => $this->translator]);
                }
            }
        }

        return $this->viewRenderer->render('settings/gdpr-delete', ['model' => $form]);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->allowAccountDelete) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.account_deletion_disabled'), 'translator' => $this->translator]);
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

        return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.account_deleted'), 'translator' => $this->translator]);
    }

    public function twoFactor(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableTwoFactorAuthentication) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available'), 'translator' => $this->translator]);
        }

        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found'), 'translator' => $this->translator]);
        }

        $qrCodeSvg = $this->twoFactorQrCodeService->generateQrCodeSvg($user);

        return $this->viewRenderer->render('settings/two-factor', [
            'user' => $user,
            'qrCodeUri' => $qrCodeSvg,
            'config' => $this->config,
            'errors' => [],
        ]);
    }

    public function twoFactorEnable(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableTwoFactorAuthentication) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available'), 'translator' => $this->translator]);
        }

        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found'), 'translator' => $this->translator]);
        }

        $user->setAuthTfEnabled(true);
        $user->setAuthTfType('google');
        $user->save();

        return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.two_factor_enabled'), 'translator' => $this->translator]);
    }

    public function twoFactorDisable(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableTwoFactorAuthentication) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available'), 'translator' => $this->translator]);
        }

        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found'), 'translator' => $this->translator]);
        }

        $user->setAuthTfEnabled(false);
        $user->setAuthTfKey(null);
        $user->setAuthTfType(null);
        $user->save();

        return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.two_factor_disabled'), 'translator' => $this->translator]);
    }

    public function confirm(ServerRequestInterface $request, string $code): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found'), 'translator' => $this->translator]);
        }

        if ($this->emailChangeService->run($code, $user)) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.email_changed'), 'translator' => $this->translator]);
        }

        return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.email_change_failed'), 'translator' => $this->translator]);
    }

    public function disconnect(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated'), 'translator' => $this->translator]);
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
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.network_disconnected'), 'translator' => $this->translator]);
        }

        return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.network_not_found'), 'translator' => $this->translator]);
    }

    public function export(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->enableGdprCompliance) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_available'), 'translator' => $this->translator]);
        }

        $identity = $request->getAttribute(IdentityInterface::class);
        if ($identity === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.not_authenticated'), 'translator' => $this->translator]);
        }

        $user = $this->userRepository->findById($identity->getId() ?? 0);
        if ($user === null) {
            return $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.user_not_found'), 'translator' => $this->translator]);
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

        $csv = implode(',', array_keys($data)) . "\n" . implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', array_values($data)));

        $response = $this->viewRenderer->render('shared/message', ['title' => $this->translator->translate('voyti.settings.data_exported'), 'translator' => $this->translator]);
        return $response;
    }
}
