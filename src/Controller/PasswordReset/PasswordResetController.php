<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\PasswordReset;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Model\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Password\ResetService;
use YiiRocks\Voyti\ViewData\PasswordReset\RequestViewData;
use Yiisoft\Http\Method;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Body;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Public "forgot password" flow: requests a recovery email via {@see RecoveryService}, then confirms
 * the emailed token and sets the new password via {@see ResetService}.
 */
final readonly class PasswordResetController
{
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private RecoveryService $passwordRecoveryService,
        private ResetService $resetPasswordService,
        private ValidatorInterface $validator,
        private EventDispatcherInterface $eventDispatcher,
        private ModuleConfig $config,
        private HydratorInterface $hydrator,
        private ResponseFactoryInterface $responseFactory,
        private FlashInterface $flash,
    ) {}

    public function confirm(
        ServerRequestInterface $request,
        #[RouteArgument]
        int $id,
        #[RouteArgument]
        string $code,
        #[Body('recovery')]
        array $formData = [],
    ): ResponseInterface {
        if (!$this->config->allowPasswordRecovery && !$this->config->allowAdminPasswordRecovery) {
            return $this->renderError('voyti.recovery.reset_disabled');
        }

        $userToken = UserToken::findByUserIdAndCodeAndType($id, $code, UserToken::TYPE_RECOVERY);

        if (
            $userToken === null
            || $userToken->isExpired($this->config->tokenRecoveryLifespan)
            || $userToken->getUser() === null
        ) {
            return $this->renderError('voyti.recovery.link_invalid');
        }

        $form = new RecoveryForm($this->config, $this->translator, RecoveryForm::SCENARIO_RESET);
        $this->hydrator->hydrate($form, $formData);

        if ($request->getMethod() === Method::POST) {
            $result = $this->validator->validate($form);
            $form->processValidationResult($result);

            if ($result->isValid()) {
                /** @var User $user */
                $user = $userToken->getUser();

                if ($this->resetPasswordService->run($form->password, $user, $userToken)) {
                    return $this->redirectWithFlash(
                        $this->url->generate('voyti/session-login'),
                        'voyti.recovery.password_changed',
                    );
                }

                $form->addError(
                    $this->translator->translate('voyti.recovery.password_previously_used', category: 'voyti'),
                    ['password'],
                );
            }
        }

        return $this->renderView('password-reset/confirm', ['form' => $form]);
    }

    public function request(
        ServerRequestInterface $request,
        #[Body('recovery')]
        array $formData = [],
    ): ResponseInterface {
        if (!$this->config->allowPasswordRecovery) {
            return $this->renderError('voyti.recovery.disabled');
        }

        $form = new RecoveryForm($this->config, $this->translator, RecoveryForm::SCENARIO_REQUEST);
        $this->hydrator->hydrate($form, $formData);

        if ($request->getMethod() === Method::POST) {
            $result = $this->validator->validate($form);
            $form->processValidationResult($result);

            if ($result->isValid()) {
                $serviceResult = $this->passwordRecoveryService->run($form->email);
                $this->flash->set(FlashType::SUCCESS, $serviceResult->getMessage());

                return $this->redirect($this->url->generate('voyti/session-login'));
            }
        }

        return $this->renderView('password-reset/request', [
            'form' => $form,
            'data' => RequestViewData::create($form, $this->config, $this->url),
        ]);
    }

}
