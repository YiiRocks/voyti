<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Privacy;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\Model\Form\Settings\ConsentForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Handles account deletion (privacy/self-service action).
 */
final readonly class PrivacyController
{
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private PasswordHasher $passwordHasher,
        private EventDispatcherInterface $eventDispatcher,
        private UrlGeneratorInterface $url,
        private FormHydrator $formHydrator,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private TerminateUserSessionsService $terminateUserSessionsService,
        private FlashNotifier $flashNotifier,
        private VoytiConfig $config,
    ) {}

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $form = new ConsentForm($this->translator, 'delete-account', 'voyti.view.delete_account.confirm_label', 'voyti');

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            /** @var User $user */
            $user = $this->currentUser->getIdentity();

            if ($this->passwordHasher->validate($form->password, $user->getPasswordHash())) {
                $userId = $user->getIdOrZero();
                $user->delete();
                $this->eventDispatcher->dispatch(new UserEvent($user, UserEvent::DELETE));
                $this->terminateUserSessionsService->run($userId);
                return $this->renderView('shared/message', [
                    'data' => [
                        'title' => $this->translator->translate('voyti.settings.account_deleted', category: 'voyti'),
                        'homeUrl' => $this->homeUrl(),
                    ],
                ]);
            }
        }

        return $this->renderView('privacy/delete', [
            'form' => $form,
            'data' => [
                'menu' => MenuView::account($this->config, $this->url, $this->translator()),
                'formSubmitUrl' => $this->url->generate('voyti/user-privacy-delete'),
            ],
        ]);
    }

    public function index(): ResponseInterface
    {
        return $this->renderView('privacy/index', [
            'data' => [
                'menu' => MenuView::account($this->config, $this->url, $this->translator()),
                'showDeleteLink' => $this->config->allowAccountDelete,
                'deleteUrl' => $this->config->allowAccountDelete ? $this->url->generate('voyti/user-privacy-delete') : null,
                'privacyLinks' => array_map(
                    fn(array $item): array => [
                        'label' => $this->translator->translate($item['label'], category: $item['category']),
                        'url' => $this->url->generate($item['route']),
                    ],
                    $this->config->privacyMenuItems,
                ),
            ],
        ]);
    }
}
