<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use YiiRocks\Voyti\Form\Rbac\AbstractAuthItemForm;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Repository\UserRepository;
use Yiisoft\Http\Method;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

abstract class AbstractAuthItemController
{
    use InputDataTrait;
    use RenderTrait;

    public function __construct(
        protected readonly TranslatorInterface $translator,
        protected readonly WebViewRenderer $viewRenderer,
        protected readonly UrlGeneratorInterface $url,
        protected readonly ValidatorInterface $validator,
        protected readonly ResponseFactoryInterface $responseFactory,
        protected readonly UserRepository $userRepository,
        protected readonly ItemsStorageInterface $itemsStorage,
        protected readonly ManagerInterface $managerInterface,
        protected readonly AssignmentsStorageInterface $assignmentsStorage,
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
            'unassignedItems' => $this->itemsStorage->getAll(),
        ]);
    }

    public function delete(string $name): ResponseInterface
    {
        if ($this->getItemType() === 'role') {
            $this->managerInterface->removeRole($name);
        } else {
            $this->managerInterface->removePermission($name);
        }
        $this->managerInterface->removeChildren($name);

        return $this->redirect($this->url->generate('voyti/' . $this->getIndexRouteName()));
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $this->queryParams($request);
        $filterName = $this->stringValue($queryParams, 'name');
        $filterDescription = $this->stringValue($queryParams, 'description');

        /** @var array<string, \Yiisoft\Rbac\Item> $items */
        $items = $this->getItemType() === 'role'
            ? $this->itemsStorage->getRoles()
            : $this->itemsStorage->getPermissions();

        if ($filterName !== '') {
            $items = array_filter(
                $items,
                static fn (\Yiisoft\Rbac\Item $item): bool => str_contains($item->getName(), $filterName),
            );
        }
        if ($filterDescription !== '') {
            $items = array_filter(
                $items,
                static fn (\Yiisoft\Rbac\Item $item): bool => str_contains($item->getDescription(), $filterDescription),
            );
        }

        return $this->renderView('rbac/' . $this->getItemType() . '/index', [
            'items' => $items,
            'filterName' => $filterName,
            'filterDescription' => $filterDescription,
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

        $users = $this->buildUserAssignmentData($item->getName());

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
            'unassignedItems' => $this->itemsStorage->getAll(),
        ]);
    }

    /**
     * @return list<array{user: \YiiRocks\Voyti\Entity\User, assigned: bool}>
     */
    private function buildUserAssignmentData(string $itemName): array
    {
        $allUsers = $this->userRepository->findAllUsers();
        /** @var array<string, true> $assignedUserIds */
        $assignedUserIds = [];
        foreach ($this->assignmentsStorage->getByItemNames([$itemName]) as $assignment) {
            $assignedUserIds[$assignment->getUserId()] = true;
        }

        $users = [];
        foreach ($allUsers as $user) {
            $users[] = [
                'user' => $user,
                'assigned' => isset($assignedUserIds[(string) $user->getId()]),
            ];
        }
        return $users;
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function processUserAssignments(array $body, string $itemName): void
    {
        $submittedIds = [];
        foreach (($body['assignedUsers'] ?? []) as $id) {
            if (is_string($id) && $id !== '') {
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

    abstract protected function getIndexRouteName(): string;

    private function redirect(string $url): ResponseInterface
    {
        return $this->responseFactory->createResponse(302)
            ->withHeader('Location', $url);
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
