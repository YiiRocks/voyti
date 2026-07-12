<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\Rbac;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Model\Form\Rbac\AbstractAuthItemForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\AuditLogService;
use Yiisoft\Http\Method;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

abstract readonly class AbstractAuthItemController
{
    use InputDataTrait;
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        protected TranslatorInterface $translator,
        protected WebViewRenderer $viewRenderer,
        protected UrlGeneratorInterface $url,
        protected ValidatorInterface $validator,
        protected ResponseFactoryInterface $responseFactory,
        protected ItemsStorageInterface $itemsStorage,
        protected ManagerInterface $managerInterface,
        protected AssignmentsStorageInterface $assignmentsStorage,
        protected FlashInterface $flash,
        protected ModuleConfig $config,
        protected AuditLogService $auditLogService,
        protected CurrentUser $currentUser,
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
                $item = $this->getItemType() === 'role' ? new Role($form->name) : new Permission($form->name);
                $item = $item->withDescription($form->description);
                if ($form->rule !== '') {
                    $item = $item->withRuleName($form->rule);
                }
                if ($item instanceof Role) {
                    $this->managerInterface->addRole($item);
                } else {
                    $this->managerInterface->addPermission($item);
                }

                /** @var list<string> $children */
                $children = $form->children;
                foreach ($children as $childName) {
                    if ($childName !== '') {
                        $this->managerInterface->addChild($form->name, $childName);
                    }
                }

                $this->auditLogService->log(
                    $this->actorId(),
                    'rbac.' . $this->getItemType() . '.create',
                    targetName: $form->name,
                );

                return $this->redirectWithFlash(
                    $this->url->generate('voyti/' . $this->getIndexRouteName()),
                    'voyti.auth_item.created',
                );
            }
            $errors = $result->getErrorMessagesIndexedByProperty();
        }

        return $this->renderView('admin/rbac/create', [
            'itemType' => $this->getItemType(),
            'model' => $form,
            'errors' => $errors,
        ]);
    }

    public function delete(string $name): ResponseInterface
    {
        $this->getItemType() === 'role'
            ? $this->managerInterface->removeRole($name)
            : $this->managerInterface->removePermission($name);

        $this->auditLogService->log(
            $this->actorId(),
            'rbac.' . $this->getItemType() . '.delete',
            targetName: $name,
        );

        return $this->redirectWithFlash(
            $this->url->generate('voyti/' . $this->getIndexRouteName()),
            'voyti.auth_item.deleted',
        );
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

        return $this->renderView('admin/rbac/index', [
            'itemType' => $this->getItemType(),
            'items' => $items,
            'filterName' => $filterName,
            'filterDescription' => $filterDescription,
            'itemChildren' => $itemChildren,
            'flash' => $this->flash,
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

                $existing = $this->getItemType() === 'role'
                    ? $this->itemsStorage->getRole($oldName)
                    : $this->itemsStorage->getPermission($oldName);
                if ($existing === null) {
                    $label = $this->getItemType() === 'role' ? 'Role' : 'Permission';
                    throw new RuntimeException("{$label} '{$oldName}' not found.");
                }
                $updated = $existing->withName($form->name)->withDescription($form->description);
                if ($form->rule !== '') {
                    $updated = $updated->withRuleName($form->rule);
                }
                if ($updated instanceof Role) {
                    $this->managerInterface->updateRole($oldName, $updated);
                } else {
                    $this->managerInterface->updatePermission($oldName, $updated);
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

                $this->auditLogService->log(
                    $this->actorId(),
                    'rbac.' . $this->getItemType() . '.update',
                    targetName: $form->name,
                    context: ['previousName' => $oldName],
                );

                return $this->redirectWithFlash(
                    $this->url->generate('voyti/' . $this->getIndexRouteName()),
                    'voyti.auth_item.updated',
                );
            }
            $errors = $result->getErrorMessagesIndexedByProperty();
        }

        return $this->renderView('admin/rbac/update', [
            'itemType' => $this->getItemType(),
            'model' => $form,
            'errors' => $errors,
            'users' => $users,
        ]);
    }

    abstract protected function createForm(): AbstractAuthItemForm;

    abstract protected function getIndexRouteName(): string;

    abstract protected function getItemType(): string;

    private function actorId(): ?int
    {
        $identity = $this->currentUser->getIdentity();
        return $identity instanceof User ? $identity->getIdOrZero() : null;
    }

    /**
     * @return list<User>
     */
    private function getAssignedUsers(string $itemName): array
    {
        $userIds = [];
        foreach ($this->assignmentsStorage->getByItemNames([$itemName]) as $assignment) {
            $userIds[] = (int) $assignment->getUserId();
        }

        return User::findByIds($userIds);
    }

    private function loadForm(AbstractAuthItemForm $form, array $body): void
    {
        $prefix = $form->getFormName();
        $data = $this->formData($body, $prefix);
        $form->name = $this->stringValue($data, 'name', $form->name);
        $form->description = $this->stringValue($data, 'description', $form->description);
        $form->rule = $this->nullableStringValue($data, 'rule') ?? $form->rule;

        /** @var mixed $children */
        $children = $data['children'] ?? null;
        $form->children = is_array($children)
            ? array_values(array_filter($children, 'is_string'))
            : $form->children;
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function processUserAssignments(array $body, string $itemName): void
    {
        $submittedIds = [];
        /** @var mixed $rawAssignedUsers */
        $rawAssignedUsers = $body['assignedUsers'] ?? [];
        $assignedUsers = (array) $rawAssignedUsers;
        array_walk(
            $assignedUsers,
            function (mixed $id) use (&$submittedIds): void {
                if (is_string($id) && $id !== '') {
                    $submittedIds[$id] = $id;
                }
            },
        );

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

}
