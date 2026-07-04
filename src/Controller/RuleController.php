<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Form\Rbac\RuleForm;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Service\Rbac\RuleEditionService;
use Yiisoft\Http\Method;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class RuleController
{
    use InputDataTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private AuthHelper $authHelper,
        private UrlGeneratorInterface $url,
        private ValidatorInterface $validator,
        private RuleEditionService $authRuleEditionService,
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $form = new RuleForm($this->translator);
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $data = $this->formData($body, $form->getFormName());
            $form->name = $this->stringValue($data, 'name');
            $form->class = $this->stringValue($data, 'class');

            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                if ($this->authRuleEditionService->create($form)) {
                    return $this->renderView('shared/message', [
                        'title' => $this->translator->translate('voyti.rule.added', category: 'voyti'),
                        'translator' => $this->translator,
                    ]);
                }
                $errors['class'] = [$this->translator->translate('voyti.rule.invalid_class', category: 'voyti')];
            } else {
                $errors = $result->getErrorMessagesIndexedByProperty();
            }
        }

        return $this->renderView('rbac/rule/create', [
            'model' => $form,
            'errors' => $errors,
        ]);
    }

    public function delete(string $name): ResponseInterface
    {
        $this->authRuleEditionService->remove($name);

        return $this->redirect($this->url->generate('voyti/rules'));
    }

    public function index(): ResponseInterface
    {
        $rules = $this->authHelper->getRuleNames();

        return $this->renderView('rbac/rule/index', [
            'rules' => $rules,
        ]);
    }

    public function update(ServerRequestInterface $request, string $name): ResponseInterface
    {
        $form = new RuleForm($this->translator);
        $form->previousName = $name;
        $form->name = $name;
        $form->class = $name;

        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $data = $this->formData($body, $form->getFormName());
            $form->name = $this->stringValue($data, 'name', $form->name);
            $form->class = $this->stringValue($data, 'class', $form->class);

            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                if ($this->authRuleEditionService->update($form)) {
                    return $this->redirect($this->url->generate('voyti/rules'));
                }
                $errors['class'] = [$this->translator->translate('voyti.rule.invalid_class', category: 'voyti')];
            } else {
                $errors = $result->getErrorMessagesIndexedByProperty();
            }
        }

        return $this->renderView('rbac/rule/update', [
            'model' => $form,
            'errors' => $errors,
        ]);
    }

    private function redirect(string $url): ResponseInterface
    {
        return $this->responseFactory->createResponse(302)
            ->withHeader('Location', $url);
    }
}
