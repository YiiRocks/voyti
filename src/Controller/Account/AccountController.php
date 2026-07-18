<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Account;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Controller\RequireUserTrait;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use Yiisoft\Http\Method;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Handles the logged-in user's own account settings form (username/email/password) and the
 * confirmation link sent when the email address changes.
 */
final readonly class AccountController
{
    use InputDataTrait;
    use RedirectTrait;
    use RenderTrait;
    use RequireUserTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private ValidatorInterface $validator,
        private UrlGeneratorInterface $url,
        private ModuleConfig $config,
        private EmailChangeService $emailChangeService,
        private HydratorInterface $hydrator,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private FlashInterface $flash,
        private PasswordHistoryService $passwordHistoryService,
    ) {}

    public function confirm(ServerRequestInterface $request, string $code): ResponseInterface
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $user;
        }

        if ($this->emailChangeService->run($code, $user)) {
            return $this->renderView('shared/message', [
                'title' => $this->translator->translate('voyti.settings.email_changed', category: 'voyti'),
            ]);
        }

        return $this->renderView('shared/message', [
            'title' => $this->translator->translate('voyti.settings.email_change_failed', category: 'voyti'),
            'translator' => $this->translator,
        ]);
    }

    public function update(ServerRequestInterface $request): ResponseInterface
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

            if (
                $result->isValid()
                && $form->password !== ''
                && $this->passwordHistoryService->wasUsedRecently($user, $form->password)
            ) {
                $form->processValidationResult($result);
                $form->addError(
                    $this->translator->translate('voyti.settings.password_previously_used', category: 'voyti'),
                    ['password'],
                );
            } elseif ($result->isValid()) {
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
                    $this->url->generate('voyti/account-update'),
                    'voyti.settings.account_details_updated',
                );
            }
        }

        return $this->renderView('account/update', [
            'model' => $form,
            'config' => $this->config,
            'flash' => $this->flash,
        ]);
    }

}
