<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\Service\Password\ResetService;
use Yiisoft\Http\Method;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class RecoveryController
{
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private RecoveryService $passwordRecoveryService,
        private ResetService $resetPasswordService,
        private UserRepository $userRepository,
        private UserTokenRepository $userTokenRepository,
        private ValidatorInterface $validator,
        private EventDispatcherInterface $eventDispatcher,
        private ModuleConfig $config,
        private HydratorInterface $hydrator,
        private ResponseFactoryInterface $responseFactory,
        private FlashInterface $flash,
    ) {
    }

    public function request(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->allowPasswordRecovery) {
            return $this->renderError('voyti.recovery.disabled');
        }

        $form = new RecoveryForm($this->config, $this->translator, RecoveryForm::SCENARIO_REQUEST);

        if ($request->getMethod() === Method::POST) {
            $body = (array) $request->getParsedBody();
            /** @var mixed $rawFormData */
            $rawFormData = $body[$form->getFormName()] ?? null;
            $formData = is_array($rawFormData) ? $rawFormData : $body;
            $this->hydrator->hydrate($form, $formData);
            $result = $this->validator->validate($form);
            $form->processValidationResult($result);

            if ($result->isValid()) {
                $serviceResult = $this->passwordRecoveryService->run($form->email);
                $this->flash->set('success', $serviceResult->getMessage());

                return $this->redirect($this->url->generate($this->config->loginRoute));
            }
        }

        return $this->renderView('recovery/request', [
            'model' => $form,
            'config' => $this->config,
        ]);
    }

    public function reset(ServerRequestInterface $request, int $id, string $code): ResponseInterface
    {
        if (!$this->config->allowPasswordRecovery && !$this->config->allowAdminPasswordRecovery) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.recovery.reset_disabled', category: 'voyti'), 'translator' => $this->translator]);
        }

        $userToken = $this->userTokenRepository->findByUserIdTypeAndCode($id, UserToken::TYPE_RECOVERY, $code);

        if ($userToken === null || $userToken->getIsExpired($this->config->tokenRecoveryLifespan) || $userToken->getUser() === null) {
            return $this->renderView('shared/message', ['title' => $this->translator->translate('voyti.recovery.link_invalid', category: 'voyti')]);
        }

        $user = $userToken->getUser();
        assert($user !== null);

        $form = new RecoveryForm($this->config, $this->translator, RecoveryForm::SCENARIO_RESET);
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = (array) $request->getParsedBody();
            /** @var mixed $rawFormData */
            $rawFormData = $body[$form->getFormName()] ?? null;
            $formData = is_array($rawFormData) ? $rawFormData : $body;
            $this->hydrator->hydrate($form, $formData);
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $this->resetPasswordService->run($form->password, $user, $userToken);

                return $this->redirectWithFlash(
                    $this->url->generate($this->config->loginRoute),
                    'voyti.recovery.password_changed',
                );
            }
            $errors = $result->getErrorMessages();
        }

        return $this->renderView('recovery/reset', ['model' => $form, 'errors' => $errors]);
    }

    protected function viewPath(): string
    {
        return $this->config->viewPath;
    }
}
