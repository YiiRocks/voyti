<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\Rbac\Rule;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\ActorIdTrait;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Model\Form\Rbac\RuleForm;
use YiiRocks\Voyti\Service\AuditLogService;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\Service\Rbac\RuleEditionService;
use YiiRocks\Voyti\ViewData\Admin\Rbac\Rule\CreateViewData;
use YiiRocks\Voyti\ViewData\Admin\Rbac\Rule\IndexViewData;
use YiiRocks\Voyti\ViewData\Admin\Rbac\Rule\UpdateViewData;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Http\Method;
use Yiisoft\Input\Http\Attribute\Parameter\Body;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Admin CRUD for RBAC rules (`Yiisoft\Rbac\RuleInterface` implementations registered by class name),
 * delegating persistence to {@see RuleEditionService}. Kept separate from {@see RbacController} since
 * rules aren't itemType-driven.
 */
final readonly class RuleController
{
    use ActorIdTrait;
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private AuthHelper $authHelper,
        private UrlGeneratorInterface $url,
        private ValidatorInterface $validator,
        private RuleEditionService $authRuleEditionService,
        private ResponseFactoryInterface $responseFactory,
        private FlashInterface $flash,
        private FlashNotifier $toast,
        private VoytiConfig $config,
        private AuditLogService $auditLogService,
        private CurrentUser $currentUser,
    ) {}

    public function create(
        ServerRequestInterface $request,
        #[Body('rule')]
        array $formData = [],
    ): ResponseInterface {
        $form = new RuleForm($this->translator);
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            /** @var string */
            $form->name = $formData['name'] ?? '';
            /** @var string */
            $form->class = $formData['class'] ?? '';

            $result = $this->validator->validate($form);
            if ($result->isValid()) {
                if ($this->authRuleEditionService->create($form)) {
                    $this->auditLogService->log($this->actorId(), 'rbac.rule.create', $request->getServerParams(), targetName: $form->name);

                    return $this->redirectWithFlash($this->url->generate('voyti/admin-rbac-rules'), 'voyti.rule.added');
                }
                $errors['class'] = [$this->translator->translate('voyti.rule.invalid_class', category: 'voyti')];
            } else {
                $errors = $result->getErrorMessagesIndexedByProperty();
            }
        }

        return $this->renderView('admin/rbac/rule/create', [
            'form' => $form,
            'data' => CreateViewData::create($errors, $this->url, $this->translator()),
        ]);
    }

    public function delete(ServerRequestInterface $request, #[RouteArgument] string $name): ResponseInterface
    {
        $this->authRuleEditionService->remove($name);
        $this->auditLogService->log($this->actorId(), 'rbac.rule.delete', $request->getServerParams(), targetName: $name);

        return $this->redirectWithFlash($this->url->generate('voyti/admin-rbac-rules'), 'voyti.rule.deleted');
    }

    public function index(): ResponseInterface
    {
        $rules = $this->authHelper->getRuleNames();

        return $this->renderView('admin/rbac/rule/index', [
            'data' => IndexViewData::create($rules, $this->url, $this->translator()),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        #[RouteArgument]
        string $name,
        #[Body('rule')]
        array $formData = [],
    ): ResponseInterface {
        $form = new RuleForm($this->translator);
        $form->previousName = $name;
        $form->name = $name;
        $form->class = $name;
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            /** @var string */
            $form->name = $formData['name'] ?? '';
            /** @var string */
            $form->class = $formData['class'] ?? '';

            $result = $this->validator->validate($form);
            if ($result->isValid()) {
                if ($this->authRuleEditionService->update($form)) {
                    $this->auditLogService->log(
                        $this->actorId(),
                        'rbac.rule.update',
                        $request->getServerParams(),
                        targetName: $form->name,
                        /** @infection-ignore-all ArrayItemRemoval: optional audit context; previous name is tracked for observability. */
                        context: ['previousName' => $name],
                    );

                    return $this->redirectWithFlash(
                        $this->url->generate('voyti/admin-rbac-rules'),
                        'voyti.rule.updated',
                    );
                }
                $errors['class'] = [$this->translator->translate('voyti.rule.invalid_class', category: 'voyti')];
            } else {
                $errors = $result->getErrorMessagesIndexedByProperty();
            }
        }

        return $this->renderView('admin/rbac/rule/update', [
            'form' => $form,
            'data' => UpdateViewData::create($form, $errors, $this->url, $this->translator()),
        ]);
    }
}
