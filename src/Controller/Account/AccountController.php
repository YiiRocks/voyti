<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Account;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\UserUpdateHelper;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
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
        private FlashNotifier $flashNotifier,
        private PasswordHistoryService $passwordHistoryService,
        private UserUpdateHelper $userUpdateHelper,
    ) {}

    public function confirm(#[RouteArgument] string $code): ResponseInterface
    {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();

        if ($this->emailChangeService->run($code, $user)) {
            return $this->renderView('shared/message', [
                'data' => [
                    'title' => $this->translator->translate('voyti.settings.email_changed', category: 'voyti'),
                    'homeUrl' => $this->homeUrl(),
                ],
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
                $changedFields = $this->userUpdateHelper->changedFields(
                    $user,
                    $form->username,
                    $form->email,
                    $form->password,
                );

                try {
                    $this->userUpdateHelper->apply(
                        $user,
                        $changedFields,
                        function (User $user) use ($form): void {
                            $user->setUsername($form->username);

                            if ($form->email !== $user->getEmail()) {
                                $form->setUser($user);
                                $this->emailChangeService->initiate(
                                    $this->config->emailChangeConfirmation,
                                    $form,
                                );
                            }
                        },
                        $form->password,
                    );

                    return $this->redirectWithFlash(
                        $this->url->generate('voyti/user-account'),
                        'voyti.settings.account_details_updated',
                    );
                } catch (ActionPreventedException $exception) {
                    $form->addError($exception->getMessage(), $exception->getErrorDetails());
                }
            }
        }

        return $this->renderView('account/update', [
            'form' => $form,
            'data' => [
                'menu' => MenuView::account($this->config, $this->url, $this->translator()),
                'formSubmitUrl' => $this->url->generate('voyti/user-account'),
            ],
        ]);
    }
}
