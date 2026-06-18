<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Http\Method;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\View\ViewInterface;
use YiiRocks\Voyti\Form\RecoveryForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\RenderTrait;
use YiiRocks\Voyti\Service\PasswordRecoveryService;
use YiiRocks\Voyti\Service\ResetPasswordService;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\TokenRepository;

final class RecoveryController
{
    use RenderTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ViewInterface $view,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly Aliases $aliases,
        private readonly UrlGeneratorInterface $url,
        private readonly PasswordRecoveryService $passwordRecoveryService,
        private readonly ResetPasswordService $resetPasswordService,
        private readonly UserRepository $userRepository,
        private readonly TokenRepository $tokenRepository,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ModuleConfig $config,
    ) {
    }

    public function request(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->allowPasswordRecovery) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.recovery.disabled'), 'translator' => $this->translator]);
        }

        $form = new RecoveryForm($this->config, RecoveryForm::SCENARIO_REQUEST);
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
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.recovery.reset_disabled'), 'translator' => $this->translator]);
        }

        $token = $this->tokenRepository->findByUserIdTypeAndCode($id, \YiiRocks\Voyti\Entity\Token::TYPE_RECOVERY, $code);

        if ($token === null || $token->getIsExpired() || $token->getUser() === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.recovery.link_invalid'), 'translator' => $this->translator]);
        }

        $form = new RecoveryForm($this->config, RecoveryForm::SCENARIO_RESET);
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $form->load($body, 'recovery');
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $this->resetPasswordService->run($form->password, $token->getUser(), $token);
                return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.recovery.password_changed'), 'translator' => $this->translator]);
            }
            $errors = $result->getErrorMessages();
        }

        return $this->renderView('recovery/reset', ['model' => $form, 'errors' => $errors]);
    }
}
