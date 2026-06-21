<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Password\ResetService;
use Yiisoft\Http\Method;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class RecoveryController
{
    use RenderTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly WebViewRenderer $viewRenderer,
        private readonly UrlGeneratorInterface $url,
        private readonly RecoveryService $passwordRecoveryService,
        private readonly ResetService $resetPasswordService,
        private readonly UserRepository $userRepository,
        private readonly UserTokenRepository $userTokenRepository,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ModuleConfig $config,
    ) {
    }

    public function request(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->allowPasswordRecovery) {
            return $this->renderError('voyti.recovery.disabled');
        }

        $form = new RecoveryForm($this->config, $this->translator, RecoveryForm::SCENARIO_REQUEST);
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $form->load($body, 'recovery');
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $serviceResult = $this->passwordRecoveryService->run($form->email);
                return $this->renderView('shared/message', ['title' => $serviceResult->getMessage()]);
            }
            $errors = $result->getErrorMessages();
        }

        return $this->renderView('recovery/request', [
            'model' => $form,
            'config' => $this->config,
            'errors' => $errors,
        ]);
    }

    public function reset(ServerRequestInterface $request, int $id, string $code): ResponseInterface
    {
        if (!$this->config->allowPasswordRecovery && !$this->config->allowAdminPasswordRecovery) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.recovery.reset_disabled', category: 'voyti'), 'translator' => $this->translator]);
        }

        $userToken = $this->userTokenRepository->findByUserIdTypeAndCode($id, UserToken::TYPE_RECOVERY, $code);

        if ($userToken === null || $userToken->getIsExpired() || $userToken->getUser() === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.recovery.link_invalid', category: 'voyti'), 'translator' => $this->translator]);
        }

        $form = new RecoveryForm($this->config, $this->translator, RecoveryForm::SCENARIO_RESET);
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $form->load($body, 'recovery');
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $this->resetPasswordService->run($form->password, $userToken->getUser(), $userToken);
                return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.recovery.password_changed', category: 'voyti'), 'translator' => $this->translator]);
            }
            $errors = $result->getErrorMessages();
        }

        return $this->renderView('recovery/reset', ['model' => $form, 'errors' => $errors]);
    }
}
