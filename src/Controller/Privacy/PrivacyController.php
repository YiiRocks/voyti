<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Privacy;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Event\Gdpr\GdprEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\Form\Settings\ConsentForm;
use YiiRocks\Voyti\Model\Form\Settings\GdprConsentForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;
use YiiRocks\Voyti\ViewData\Privacy\AnonymizeViewData;
use YiiRocks\Voyti\ViewData\Privacy\DeleteViewData;
use YiiRocks\Voyti\ViewData\Privacy\GdprConsentViewData;
use YiiRocks\Voyti\ViewData\Privacy\IndexViewData;
use YiiRocks\Voyti\ViewData\Shared\MessageViewData;
use Yiisoft\Http\Header;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Body;
use Yiisoft\Json\Json;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Handles GDPR-related self-service actions on the current user's account: consent capture,
 * data export, anonymization, and account deletion.
 */
final readonly class PrivacyController
{
    use RedirectTrait;
    use RenderTrait;

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
    ) {}

    public function anonymize(
        ServerRequestInterface $request,
        #[Body('anonymize')]
        array $formData = [],
    ): ResponseInterface {
        $form = new ConsentForm($this->translator, 'anonymize', 'voyti.view.anonymize.confirm_label');
        $this->hydrator->hydrate($form, $formData);

        if ($request->getMethod() === Method::POST) {
            $result = $this->validator->validate($form);

            /** @var User $user */
            $user = $this->currentUser->getIdentity();

            if ($result->isValid() && $this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                $prefix = $this->config->gdprAnonymizePrefix . ($user->getId() ?? '');
                $user->setEmail($prefix . '@example.com');
                $user->setUsername($prefix);
                $user->setAnonymized(true);
                $user->setBlockedAt(time());
                $user->setAuthKey(Random::string());
                $user->save();
                $this->eventDispatcher->dispatch(new GdprEvent($user));
                $this->terminateUserSessionsService->run($user->getIdOrZero());
                return $this->renderView('shared/message', [
                    'data' => new MessageViewData(
                        title: $this->translator->translate('voyti.settings.personal_info_removed', category: 'voyti'),
                        homeUrl: $this->homeUrl(),
                    ),
                ]);
            }
        }

        return $this->renderView('privacy/anonymize', ['form' => $form, 'data' => AnonymizeViewData::create($this->url)]);
    }

    public function delete(
        ServerRequestInterface $request,
        #[Body('delete-account')]
        array $formData = [],
    ): ResponseInterface {
        $form = new ConsentForm($this->translator, 'delete-account', 'voyti.view.delete_account.confirm_label');
        $this->hydrator->hydrate($form, $formData);

        if ($request->getMethod() === Method::POST) {
            $result = $this->validator->validate($form);

            /** @var User $user */
            $user = $this->currentUser->getIdentity();

            if ($result->isValid() && $this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                $userId = $user->getIdOrZero();
                $user->delete();
                $this->eventDispatcher->dispatch(new UserEvent($user, UserEvent::DELETE));
                $this->terminateUserSessionsService->run($userId);
                return $this->renderView('shared/message', [
                    'data' => new MessageViewData(
                        title: $this->translator->translate('voyti.settings.account_deleted', category: 'voyti'),
                        homeUrl: $this->homeUrl(),
                    ),
                ]);
            }
        }

        return $this->renderView('privacy/delete', ['form' => $form, 'data' => DeleteViewData::create($this->url)]);
    }

    public function export(): ResponseInterface
    {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();

        $values = array_map(
            fn(string $property): mixed => $this->exportValue($user, $property),
            $this->config->gdprExportProperties,
        );
        /** @var array<array-key, mixed> $data */
        $data = array_filter(
            array_combine($this->config->gdprExportProperties, $values),
            static fn(mixed $v): bool => $v !== null,
        );

        $json = Json::encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        $response = $this->responseFactory->createResponse(Status::OK)
            ->withHeader(Header::CONTENT_TYPE, 'application/json; charset=UTF-8')
            ->withHeader(Header::CONTENT_DISPOSITION, 'attachment; filename="user-data-export.json"');
        $response->getBody()->write($json);

        return $response;
    }

    public function gdprConsent(
        ServerRequestInterface $request,
        #[Body('gdpr-consent')]
        array $formData = [],
    ): ResponseInterface {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();

        $form = new GdprConsentForm($this->translator);
        $this->hydrator->hydrate($form, $formData);

        if ($request->getMethod() === Method::POST) {
            if ($form->consent && !$user->isGdprConsent()) {
                $user->setGdprConsent(true);
                $user->setGdprConsentDate(time());
                $user->save();
            }
            return $this->redirectWithFlash(
                $this->url->generate('voyti/user-privacy-gdpr-consent'),
                'voyti.settings.gdpr_consent_saved',
            );
        }

        $form->consent = $user->isGdprConsent();
        $form->consentDate = $user->getGdprConsentDate();
        $form->timezone = $user->getProfile()?->getTimezone();

        return $this->renderView('privacy/gdpr-consent', [
            'form' => $form,
            'data' => GdprConsentViewData::create($form, $this->url, $this->translator->getLocale()),
        ]);
    }

    public function index(): ResponseInterface
    {
        return $this->renderView('privacy/index', [
            'data' => IndexViewData::create($this->config, $this->url, $this->translator()),
        ]);
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
                static fn(UserSessions $entry): array => [
                    'ip' => $entry->getIp(),
                    'user_agent' => $entry->getUserAgent(),
                    'created_at' => $entry->getCreatedAt(),
                    'updated_at' => $entry->getUpdatedAt(),
                ],
                UserSessions::findByUserId($user->getIdOrZero()),
            ),
            'userSocialAccount' => array_map(
                static fn(UserSocialAccount $account): array => [
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
