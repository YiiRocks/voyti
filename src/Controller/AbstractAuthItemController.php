<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Http\Method;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\ViewRenderer;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Validator\ValidatorInterface;
use YiiRocks\Voyti\Form\AbstractAuthItemForm;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Service\AuthItemEditionService;

abstract class AbstractAuthItemController
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        protected readonly ViewRenderer $viewRenderer,
        protected readonly AuthHelper $authHelper,
        protected readonly UrlGeneratorInterface $url,
        protected readonly ValidatorInterface $validator,
        protected readonly AuthItemEditionService $authItemEditionService,
    ) {
    }

    abstract protected function getItemType(): string;

    abstract protected function createForm(): AbstractAuthItemForm;

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $filterName = $queryParams['name'] ?? '';
        $filterDescription = $queryParams['description'] ?? '';

        $items = $this->getItemType() === 'role'
            ? $this->authHelper->getRoles()
            : $this->authHelper->getPermissions();

        if ($filterName !== '') {
            $items = array_filter($items, fn($item) => str_contains($item->getName(), $filterName));
        }
        if ($filterDescription !== '') {
            $items = array_filter($items, fn($item) => str_contains($item->getDescription(), $filterDescription));
        }

        return $this->viewRenderer->render($this->getItemType() . '/index', [
            'items' => $items,
            'filterName' => $filterName,
            'filterDescription' => $filterDescription,
        ]);
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $form = $this->createForm();
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $this->loadForm($form, $body);
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                if ($this->authItemEditionService->create($form)) {
                    return $this->viewRenderer->render('shared/message', [
                        'title' => $this->translator->translate('voyti.auth_item.' . $this->getItemType() . '_created'),
                        'translator' => $this->translator,
                    ]);
                }
            }
            $errors = $result->getErrorMessagesIndexedByProperty();
        }

        return $this->viewRenderer->render($this->getItemType() . '/create', [
            'model' => $form,
            'errors' => $errors,
            'unassignedItems' => $this->authHelper->getAllItems(),
        ]);
    }

    public function update(ServerRequestInterface $request, string $name): ResponseInterface
    {
        $form = $this->createForm();
        $item = $this->getItemType() === 'role'
            ? $this->authHelper->getRole($name)
            : $this->authHelper->getPermission($name);

        if ($item === null) {
            return $this->viewRenderer->render('shared/message', [
                'title' => $this->translator->translate('voyti.auth_item.not_found'),
                'translator' => $this->translator,
            ]);
        }

        $form->itemName = $item->getName();
        $form->name = $item->getName();
        $form->description = $item->getDescription();
        $form->rule = $item->getRuleName() ?? '';
        $form->children = array_keys($this->authHelper->getChildren($item->getName()));

        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $request->getParsedBody();
            $this->loadForm($form, $body);
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                if ($this->authItemEditionService->update($form)) {
                    return $this->viewRenderer->render('shared/message', [
                        'title' => $this->translator->translate('voyti.auth_item.' . $this->getItemType() . '_updated'),
                        'translator' => $this->translator,
                    ]);
                }
            }
            $errors = $result->getErrorMessagesIndexedByProperty();
        }

        return $this->viewRenderer->render($this->getItemType() . '/update', [
            'model' => $form,
            'errors' => $errors,
            'unassignedItems' => $this->authHelper->getAllItems(),
        ]);
    }

    public function delete(string $name): ResponseInterface
    {
        $this->authItemEditionService->delete($name, $this->getItemType());

        return $this->viewRenderer->render('shared/message', [
            'title' => $this->translator->translate('voyti.auth_item.' . $this->getItemType() . '_deleted'),
            'translator' => $this->translator,
        ]);
    }

    protected function loadForm(AbstractAuthItemForm $form, array $body): void
    {
        $prefix = $form->getFormName();
        $data = $body[$prefix] ?? [];
        $form->name = $data['name'] ?? $form->name;
        $form->description = $data['description'] ?? $form->description;
        $form->rule = $data['rule'] ?? $form->rule;
        $form->children = $data['children'] ?? $form->children;
    }
}
