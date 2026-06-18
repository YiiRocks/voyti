<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Http\Method;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\ViewInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Validator\ValidatorInterface;
use YiiRocks\Voyti\Form\RuleForm;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\RenderTrait;
use YiiRocks\Voyti\Service\AuthRuleEditionService;

final class RuleController
{
    use RenderTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ViewInterface $view,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly Aliases $aliases,
        private readonly AuthHelper $authHelper,
        private readonly UrlGeneratorInterface $url,
        private readonly ValidatorInterface $validator,
        private readonly AuthRuleEditionService $authRuleEditionService,
    ) {
    }

    public function index(): ResponseInterface
    {
        $rules = $this->authHelper->getRuleNames();

        return $this->renderView('rule/index', [
            'rules' => $rules,
        ]);
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $form = new RuleForm();
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $data = $body['rule'] ?? [];
            $form->name = $data['name'] ?? '';
            $form->class = $data['class'] ?? '';

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

        return $this->renderView('rule/create', [
            'model' => $form,
            'errors' => $errors,
        ]);
    }

    public function update(ServerRequestInterface $request, string $name): ResponseInterface
    {
        $form = new RuleForm();
        $form->previousName = $name;
        $form->name = $name;
        $form->class = $name;

        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $data = $body['rule'] ?? [];
            $form->name = $data['name'] ?? $form->name;
            $form->class = $data['class'] ?? $form->class;

            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                if ($this->authRuleEditionService->update($form)) {
                    return $this->renderView('shared/message', [
                        'title' => $this->translator->translate('voyti.rule.updated', category: 'voyti'),
                        'translator' => $this->translator,
                    ]);
                }
                $errors['class'] = [$this->translator->translate('voyti.rule.invalid_class', category: 'voyti')];
            } else {
                $errors = $result->getErrorMessagesIndexedByProperty();
            }
        }

        return $this->renderView('rule/update', [
            'model' => $form,
            'errors' => $errors,
        ]);
    }

    public function delete(string $name): ResponseInterface
    {
        $this->authRuleEditionService->remove($name);

        return $this->renderView('shared/message', [
            'title' => $this->translator->translate('voyti.rule.removed', category: 'voyti'),
            'translator' => $this->translator,
        ]);
    }
}
