<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Account;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\ViewData\Account\UpdateViewData;
use YiiRocks\Voyti\ViewData\Shared\MessageViewData;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Handles the logged-in user's own account settings form (username/email/password) and the
 * confirmation link sent when the email address changes.
 */
final readonly class AccountController
{
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private VoytiConfig $config,
        private EmailChangeService $emailChangeService,
        private FormHydrator $formHydrator,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private FlashInterface $flash,
        private FlashNotifier $toast,
        private PasswordHistoryService $passwordHistoryService,
    ) {}

    public function confirm(#[RouteArgument] string $code): ResponseInterface
    {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();

        if ($this->emailChangeService->run($code, $user)) {
            return $this->renderView('shared/message', [
                'data' => new MessageViewData(
                    title: $this->translator->translate('voyti.settings.email_changed', category: 'voyti'),
                    homeUrl: $this->homeUrl(),
                ),
            ]);
        }

        return $this->renderError('voyti.settings.email_change_failed');
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();

        $form = new SettingsForm($this->config, $this->translator);
        $form->username = $user->getUsername();
        $form->email = $user->getEmail();

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            if (
                $form->password !== ''
                && $this->passwordHistoryService->wasUsedRecently($user, $form->password)
            ) {
                $form->addError(
                    $this->translator->translate('voyti.settings.password_previously_used', category: 'voyti'),
                    ['password'],
                );
            } else {
                $user->setUsername($form->username);

                if ($form->email !== $user->getEmail()) {
                    $form->setUser($user);
                    $this->emailChangeService->initiate(
                        $this->config->emailChangeConfirmation,
                        $form,
                    );
                }

                if ($form->password !== '') {
                    $this->passwordHistoryService->applyPasswordChange($user, $form->password);
                } else {
                    $user->setUpdatedAt(time());
                    $user->save();
                }

                return $this->redirectWithFlash(
                    $this->url->generate('voyti/user-account'),
                    'voyti.settings.account_details_updated',
                );
            }
        }

        return $this->renderView('account/update', [
            'form' => $form,
            'data' => UpdateViewData::create(
                $this->config,
                $this->url,
                $this->translator(),
            ),
        ]);
    }
}
