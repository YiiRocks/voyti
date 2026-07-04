<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Form\Rbac\AbstractAuthItemForm;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Repository\UserRepository;
use Yiisoft\Http\Method;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

abstract readonly class AbstractAuthItemController
{
    use InputDataTrait;
    use RenderTrait;

    public function __construct(
        protected TranslatorInterface $translator,
        protected WebViewRenderer $viewRenderer,
        protected UrlGeneratorInterface $url,
        protected ValidatorInterface $validator,
        protected ResponseFactoryInterface $responseFactory,
        protected UserRepository $userRepository,
        protected ItemsStorageInterface $itemsStorage,
        protected ManagerInterface $managerInterface,
        protected AssignmentsStorageInterface $assignmentsStorage,
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
                if ($this->getItemType() === 'role') {
                    $item = (new Role($form->name))->withDescription($form->description);
                    if ($form->rule !== '') {
                        $item = $item->withRuleName($form->rule);
                    }
                    $this->managerInterface->addRole($item);
                } else {
                    $item = (new Permission($form->name))->withDescription($form->description);
                    if ($form->rule !== '') {
                        $item = $item->withRuleName($form->rule);
                    }
                    $this->managerInterface->addPermission($item);
                }

                /** @var list<string> $children */
                $children = $form->children;
                foreach ($children as $childName) {
                    if ($childName !== '') {
                        $this->managerInterface->addChild($form->name, $childName);
                    }
                }

                return $this->redirect($this->url->generate('voyti/' . $this->getIndexRouteName()));
            }
            $errors = $result->getErrorMessagesIndexedByProperty();
        }

        return $this->renderView('rbac/' . $this->getItemType() . '/create', [
            'model' => $form,
            'errors' => $errors,
        ]);
    }

    public function delete(string $name): ResponseInterface
    {
        if ($this->getItemType() === 'role') {
            $this->managerInterface->removeRole($name);
        } else {
            $this->managerInterface->removePermission($name);
        }

        return $this->redirect($this->url->generate('voyti/' . $this->getIndexRouteName()));
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $this->queryParams($request);
        $filterName = $this->stringValue($queryParams, 'name');
        $filterDescription = $this->stringValue($queryParams, 'description');

        /** @var array<string, Item> $items */
        $items = $this->getItemType() === 'role'
            ? $this->itemsStorage->getRoles()
            : $this->itemsStorage->getPermissions();

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

        $itemChildren = [];
        foreach ($items as $item) {
            $itemChildren[$item->getName()] = array_keys($this->itemsStorage->getDirectChildren($item->getName()));
        }

        return $this->renderView('rbac/' . $this->getItemType() . '/index', [
            'items' => $items,
            'filterName' => $filterName,
            'filterDescription' => $filterDescription,
            'itemChildren' => $itemChildren,
        ]);
    }

    public function update(ServerRequestInterface $request, string $name): ResponseInterface
    {
        $form = $this->createForm();
        $item = $this->getItemType() === 'role'
            ? $this->managerInterface->getRole($name)
            : $this->managerInterface->getPermission($name);

        if ($item === null) {
            return $this->renderError('voyti.auth_item.not_found');
        }

        $form->itemName = $item->getName();
        $form->name = $item->getName();
        $form->description = $item->getDescription();
        $form->rule = $item->getRuleName() ?? '';
        $form->children = array_keys($this->itemsStorage->getDirectChildren($item->getName()));

        $users = $this->getAssignedUsers($item->getName());

        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->loadForm($form, $body);
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $oldName = $form->itemName !== '' ? $form->itemName : $form->name;

                if ($this->getItemType() === 'role') {
                    $role = $this->itemsStorage->getRole($oldName);
                    if ($role === null) {
                        throw new RuntimeException("Role '{$oldName}' not found.");
                    }
                    $role = $role->withName($form->name)->withDescription($form->description);
                    if ($form->rule !== '') {
                        $role = $role->withRuleName($form->rule);
                    }
                    $this->managerInterface->updateRole($oldName, $role);
                } else {
                    $perm = $this->itemsStorage->getPermission($oldName);
                    if ($perm === null) {
                        throw new RuntimeException("Permission '{$oldName}' not found.");
                    }
                    $perm = $perm->withName($form->name)->withDescription($form->description);
                    if ($form->rule !== '') {
                        $perm = $perm->withRuleName($form->rule);
                    }
                    $this->managerInterface->updatePermission($oldName, $perm);
                }

                $this->managerInterface->removeChildren($form->name);
                /** @var list<string> $children */
                $children = $form->children;
                foreach ($children as $childName) {
                    if ($childName !== '') {
                        $this->managerInterface->addChild($form->name, $childName);
                    }
                }

                $this->processUserAssignments($body, $form->name);

                return $this->redirect($this->url->generate('voyti/' . $this->getIndexRouteName()));
            }
            $errors = $result->getErrorMessagesIndexedByProperty();
        }

        return $this->renderView('rbac/' . $this->getItemType() . '/update', [
            'model' => $form,
            'errors' => $errors,
            'users' => $users,
        ]);
    }

    abstract protected function createForm(): AbstractAuthItemForm;

    abstract protected function getIndexRouteName(): string;

    abstract protected function getItemType(): string;

    /**
     * @return list<User>
     */
    private function getAssignedUsers(string $itemName): array
    {
        $userIds = [];
        foreach ($this->assignmentsStorage->getByItemNames([$itemName]) as $assignment) {
            /** @infection-ignore-all CastInt: SQLite's IN() comparison is untyped, so a numeric-string user ID matches the INTEGER column just as well; the cast exists to satisfy findByIds()'s list<int> contract, not to change query results. */
            $userIds[] = (int) $assignment->getUserId();
        }

        return $this->userRepository->findByIds($userIds);
    }

    private function loadForm(AbstractAuthItemForm $form, array $body): void
    {
        $prefix = $form->getFormName();
        $data = $this->formData($body, $prefix);
        $form->name = $this->stringValue($data, 'name', $form->name);
        $form->description = $this->stringValue($data, 'description', $form->description);
        $form->rule = $this->nullableStringValue($data, 'rule') ?? $form->rule;

        $children = $data['children'] ?? $form->children;
        /** @infection-ignore-all UnwrapArrayValues: only ever consumed via foreach, which doesn't care about key contiguity; array_values() exists to honor the list<string> contract, not to change iteration results. */
        $form->children = is_array($children) ? array_values(array_filter($children, 'is_string')) : $form->children;
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function processUserAssignments(array $body, string $itemName): void
    {
        $submittedIds = [];
        foreach (($body['assignedUsers'] ?? []) as $id) {
            if (is_string($id) && $id !== '') {
                /** @infection-ignore-all TrueValue: only isset() is ever checked against this entry, which is indifferent to true vs false. */
                $submittedIds[$id] = true;
            }
        }

        $currentAssignments = $this->assignmentsStorage->getByItemNames([$itemName]);
        foreach ($currentAssignments as $assignment) {
            $uid = $assignment->getUserId();
            if (!isset($submittedIds[$uid])) {
                $this->assignmentsStorage->remove($itemName, $uid);
            }
            unset($submittedIds[$uid]);
        }

        foreach ($submittedIds as $uid => $_) {
            $this->managerInterface->assign($itemName, (int) $uid);
        }
    }

    private function redirect(string $url): ResponseInterface
    {
        return $this->responseFactory->createResponse(302)
            ->withHeader('Location', $url);
    }
}
