<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Form\Rbac\AbstractAuthItemForm;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Service\Rbac\ItemEditionService;
use Yiisoft\Rbac\Item;
use Yiisoft\Http\Method;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

abstract class AbstractAuthItemController
{
    use RenderTrait;
    use InputDataTrait;

    public function __construct(
        protected readonly TranslatorInterface $translator,
        protected readonly WebViewRenderer $viewRenderer,
        protected readonly AuthHelper $authHelper,
        protected readonly UrlGeneratorInterface $url,
        protected readonly ValidatorInterface $validator,
        protected readonly ItemEditionService $authItemEditionService,
    ) {
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $form = $this->createForm();
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = (array) $request->getParsedBody();
            $this->loadForm($form, $body);
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                if ($this->authItemEditionService->create($form)) {
                    return $this->renderSuccess('voyti.auth_item.' . $this->getItemType() . '_created');
                }
            }
            $errors = $result->getErrorMessagesIndexedByProperty();
        }

        return $this->renderView($this->getItemType() . '/create', [
            'model' => $form,
            'errors' => $errors,
            'unassignedItems' => $this->authHelper->getAllItems(),
        ]);
    }

    public function delete(string $name): ResponseInterface
    {
        $this->authItemEditionService->delete($name, $this->getItemType());

        return $this->renderSuccess('voyti.auth_item.' . $this->getItemType() . '_deleted');
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $this->queryParams($request);
        $filterName = $this->stringValue($queryParams, 'name');
        $filterDescription = $this->stringValue($queryParams, 'description');

        /** @var array<string, Item> $items */
        $items = $this->getItemType() === 'role'
            ? $this->authHelper->getRoles()
            : $this->authHelper->getPermissions();

        if ($filterName !== '') {
            $items = array_filter(
                $items,
                static fn (Item $item): bool => str_contains($item->getName(), $filterName),
            );
        }
        if ($filterDescription !== '') {
            $items = array_filter(
                $items,
                static fn (Item $item): bool => str_contains($item->getDescription(), $filterDescription),
            );
        }

        return $this->renderView($this->getItemType() . '/index', [
            'items' => $items,
            'filterName' => $filterName,
            'filterDescription' => $filterDescription,
        ]);
    }

    public function update(ServerRequestInterface $request, string $name): ResponseInterface
    {
        $form = $this->createForm();
        $item = $this->getItemType() === 'role'
            ? $this->authHelper->getRole($name)
            : $this->authHelper->getPermission($name);

        if ($item === null) {
            return $this->renderError('voyti.auth_item.not_found');
        }

        $form->itemName = $item->getName();
        $form->name = $item->getName();
        $form->description = $item->getDescription();
        $form->rule = $item->getRuleName() ?? '';
        $form->children = array_keys($this->authHelper->getChildren($item->getName()));

        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->loadForm($form, $body);
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                if ($this->authItemEditionService->update($form)) {
                    return $this->renderSuccess('voyti.auth_item.' . $this->getItemType() . '_updated');
                }
            }
            $errors = $result->getErrorMessagesIndexedByProperty();
        }

        return $this->renderView($this->getItemType() . '/update', [
            'model' => $form,
            'errors' => $errors,
            'unassignedItems' => $this->authHelper->getAllItems(),
        ]);
    }

    abstract protected function createForm(): AbstractAuthItemForm;

    abstract protected function getItemType(): string;

    protected function loadForm(AbstractAuthItemForm $form, array $body): void
    {
        $prefix = $form->getFormName();
        $data = $this->formData($body, $prefix);
        $form->name = $this->stringValue($data, 'name', $form->name);
        $form->description = $this->stringValue($data, 'description', $form->description);
        $form->rule = $this->nullableStringValue($data, 'rule') ?? $form->rule;

        $children = $data['children'] ?? $form->children;
        $form->children = is_array($children) ? array_values(array_filter($children, 'is_string')) : $form->children;
    }
}
