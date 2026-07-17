<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Privacy;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Controller\RequireUserTrait;
use YiiRocks\Voyti\Event\Gdpr\GdprEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Model\Form\Settings\ConsentForm;
use YiiRocks\Voyti\Model\Form\Settings\GdprConsentForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSession;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;
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

final readonly class PrivacyController
{
    use InputDataTrait;
    use RedirectTrait;
    use RenderTrait;
    use RequireUserTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private PasswordHasher $passwordHasher,
        private ValidatorInterface $validator,
        private EventDispatcherInterface $eventDispatcher,
        private UrlGeneratorInterface $url,
        private ModuleConfig $config,
        private HydratorInterface $hydrator,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private TerminateUserSessionsService $terminateUserSessionsService,
        private FlashInterface $flash,
    ) {
    }

    public function anonymize(ServerRequestInterface $request): ResponseInterface
    {
        $form = new ConsentForm($this->translator, 'anonymize', 'voyti.view.anonymize.confirm_label');

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $result = $this->validator->validate($form);

            $identity = $this->currentUser->getIdentity();
            if ($result->isValid() && !($identity instanceof GuestIdentityInterface)) {
                $user = User::findById((int) ($identity->getId() ?? 0));
                if ($user !== null &&         $this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
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

        return $this->renderView('privacy/anonymize', ['model' => $form]);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $form = new ConsentForm($this->translator, 'delete-account', 'voyti.view.delete_account.confirm_label');

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $result = $this->validator->validate($form);

            $identity = $this->currentUser->getIdentity();
            if ($result->isValid() && !($identity instanceof GuestIdentityInterface)) {
                $user = User::findById((int) ($identity->getId() ?? 0));
                if ($user !== null && $this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                    $userId = $user->getIdOrZero();
                    $user->delete();
                    $this->eventDispatcher->dispatch(new UserEvent($user, UserEvent::DELETE));
                    $this->terminateUserSessionsService->run($userId);
                    return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.settings.account_deleted', category: 'voyti')]);
                }
            }
        }

        return $this->renderView('privacy/delete', ['model' => $form]);
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
            $user = User::findById((int) ($identity->getId() ?? 0));
            if ($user !== null) {
                if ($form->consent && !$user->isGdprConsent()) {
                    $user->setGdprConsent(true);
                    $user->setGdprConsentDate(time());
                    $user->save();
                }
                return $this->redirectWithFlash(
                    $this->url->generate('voyti/privacy-gdpr-consent'),
                    'voyti.settings.gdpr_consent_saved',
                );
            }
        }

        if (!($identity instanceof GuestIdentityInterface)) {
            $user = User::findById((int) ($identity->getId() ?? 0));
            if ($user !== null) {
                $form->consent = $user->isGdprConsent();
                $form->consentDate = $user->getGdprConsentDate();
                $form->timezone = $user->getProfile()?->getTimezone();
            }
        }

        return $this->renderView('privacy/gdpr-consent', ['model' => $form, 'flash' => $this->flash]);
    }

    public function index(): ResponseInterface
    {
        return $this->renderView('privacy/index', ['config' => $this->config]);
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
            'userProfile.birthday' => $user->getProfile()?->getBirthday()?->format('Y-m-d'),
            'userSessions' => array_map(
                static fn (UserSession $entry): array => [
                    'ip' => $entry->getIp(),
                    'user_agent' => $entry->getUserAgent(),
                    'created_at' => $entry->getCreatedAt(),
                    'updated_at' => $entry->getUpdatedAt(),
                ],
                UserSession::findByUserId($user->getIdOrZero()),
            ),
            'userSocialAccount' => array_map(
                static fn (UserSocialAccount $account): array => [
                    'provider' => $account->getProvider(),
                    'username' => $account->getUsername(),
                    'email' => $account->getEmail(),
                    'created_at' => $account->getCreatedAt(),
                    'data' => $account->getDecodedData(),
                ],
                UserSocialAccount::findByUserId($user->getIdOrZero()),
            ),
            default => null,
        };
    }
}
