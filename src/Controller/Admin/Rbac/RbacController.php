<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\Rbac;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use YiiRocks\Voyti\Controller\ActorIdTrait;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Model\Form\Rbac\AuthItemForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\AuditLogService;
use YiiRocks\Voyti\ViewData\Admin\Rbac\CreateViewData;
use YiiRocks\Voyti\ViewData\Admin\Rbac\IndexViewData;
use YiiRocks\Voyti\ViewData\Admin\Rbac\UpdateViewData;
use Yiisoft\Http\Method;
use Yiisoft\Input\Http\Attribute\Parameter\Body;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Generic CRUD controller for RBAC roles and permissions: every action takes an `$itemType`
 * ('role'|'permission') and `$indexRouteName` and branches internally rather than being split into
 * separate role/permission controllers. Also manages an item's child items and user assignments.
 * Because {@see ManagerInterface} has no transaction support, a failure while attaching children
 * after the item itself is committed is reported as a form error rather than rolled back.
 */
final readonly class RbacController
{
    use ActorIdTrait;
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private ValidatorInterface $validator,
        private ResponseFactoryInterface $responseFactory,
        private ItemsStorageInterface $itemsStorage,
        private ManagerInterface $managerInterface,
        private AssignmentsStorageInterface $assignmentsStorage,
        private FlashInterface $flash,
        private ModuleConfig $config,
        private AuditLogService $auditLogService,
        private CurrentUser $currentUser,
    ) {}

    public function create(
        ServerRequestInterface $request,
        #[Body('name')]
        string $name = '',
        #[Body('description')]
        string $description = '',
        #[Body('rule')]
        string $rule = '',
        #[Body('children')]
        ?array $children = null,
        #[RouteArgument]
        string $itemType = '',
        #[RouteArgument]
        string $indexRouteName = '',
    ): ResponseInterface {
        $form = $this->createForm($itemType);
        $availableChildren = $this->getAvailableChildren($itemType);
        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $this->loadForm($form, $name, $description, $rule, $children);

            $result = $this->validator->validate($form);
            if ($result->isValid()) {
                $item = $itemType === 'role' ? new Role($form->name) : new Permission($form->name);
                $item = $item->withDescription($form->description);
                if ($form->rule !== '') {
                    $item = $item->withRuleName($form->rule);
                }
                if ($item instanceof Role) {
                    $this->managerInterface->addRole($item);
                } else {
                    $this->managerInterface->addPermission($item);
                }

                try {
                    /** @var list<string> $childNames */
                    $childNames = $form->children;
                    foreach ($childNames as $childName) {
                        if ($childName !== '') {
                            $this->managerInterface->addChild($form->name, $childName);
                        }
                    }
                } catch (RuntimeException $exception) {
                    // Item itself is already committed; no transaction support exists to roll it back.
                    $errors = ['children' => [$exception->getMessage()]];
                }

                if ($errors === []) {
                    $this->auditLogService->log(
                        $this->actorId(),
                        'rbac.' . $itemType . '.create',
                        targetName: $form->name,
                    );

                    return $this->redirectWithFlash(
                        $this->url->generate('voyti/' . $indexRouteName),
                        'voyti.auth_item.created',
                    );
                }
            } else {
                $errors = $result->getErrorMessagesIndexedByProperty();
            }
        }

        return $this->renderView('admin/rbac/create', [
            'form' => $form,
            'data' => CreateViewData::create($itemType, $form, $availableChildren, $errors, $this->url, $this->translator()),
        ]);
    }

    public function delete(#[RouteArgument] string $name, #[RouteArgument] string $itemType, #[RouteArgument] string $indexRouteName): ResponseInterface
    {
        $itemType === 'role'
            ? $this->managerInterface->removeRole($name)
            : $this->managerInterface->removePermission($name);

        $this->auditLogService->log(
            $this->actorId(),
            'rbac.' . $itemType . '.delete',
            targetName: $name,
        );

        return $this->redirectWithFlash(
            $this->url->generate('voyti/' . $indexRouteName),
            'voyti.auth_item.deleted',
        );
    }

    public function index(
        #[Query('name')]
        string $filterName = '',
        #[Query('description')]
        string $filterDescription = '',
        #[RouteArgument]
        string $itemType = '',
        #[RouteArgument]
        string $indexRouteName = '',
    ): ResponseInterface {
        /** @var array<string, Item> $items */
        $items = $itemType === 'role'
            ? $this->itemsStorage->getRoles()
            : $this->itemsStorage->getPermissions();

        if ($filterName !== '') {
            $items = array_filter(
                $items,
                static fn(Item $item): bool => str_contains($item->getName(), $filterName),
            );
        }
        if ($filterDescription !== '') {
            $items = array_filter(
                $items,
                static fn(Item $item): bool => str_contains($item->getDescription(), $filterDescription),
            );
        }

        $itemChildren = [];
        foreach ($items as $item) {
            $itemChildren[$item->getName()] = array_keys($this->itemsStorage->getDirectChildren($item->getName()));
        }

        return $this->renderView('admin/rbac/index', [
            'data' => IndexViewData::create(
                $itemType,
                $items,
                $itemChildren,
                $filterName,
                $filterDescription,
                $this->url,
                $this->translator(),
            ),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        #[RouteArgument]
        string $name,
        #[Body('name')]
        string $formName = '',
        #[Body('description')]
        string $description = '',
        #[Body('rule')]
        string $rule = '',
        #[Body('children')]
        ?array $children = null,
        #[Body('assignedUsers')]
        ?array $assignedUsers = null,
        #[RouteArgument]
        string $itemType = '',
        #[RouteArgument]
        string $indexRouteName = '',
    ): ResponseInterface {
        $form = $this->createForm($itemType);
        $item = $itemType === 'role'
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
        $availableChildren = $this->getAvailableChildren($itemType, $item->getName());

        $users = $this->getAssignedUsers($item->getName());

        $errors = [];

        if ($request->getMethod() === Method::POST) {
            $this->loadForm($form, $formName, $description, $rule, $children);
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $oldName = $form->itemName !== '' ? $form->itemName : $form->name;

                $existing = $itemType === 'role'
                    ? $this->itemsStorage->getRole($oldName)
                    : $this->itemsStorage->getPermission($oldName);
                if ($existing === null) {
                    $label = $itemType === 'role' ? 'Role' : 'Permission';
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

                try {
                    $this->managerInterface->removeChildren($form->name);
                    /** @var list<string> $childNames */
                    $childNames = $form->children;
                    foreach ($childNames as $childName) {
                        if ($childName !== '') {
                            $this->managerInterface->addChild($form->name, $childName);
                        }
                    }
                } catch (RuntimeException $exception) {
                    // Item update itself is already committed; no transaction support exists to roll it back.
                    $errors = ['children' => [$exception->getMessage()]];
                }

                if ($errors === []) {
                    $this->processUserAssignments($assignedUsers ?? [], $form->name);

                    $this->auditLogService->log(
                        $this->actorId(),
                        'rbac.' . $itemType . '.update',
                        targetName: $form->name,
                        context: ['previousName' => $oldName],
                    );

                    return $this->redirectWithFlash(
                        $this->url->generate('voyti/' . $indexRouteName),
                        'voyti.auth_item.updated',
                    );
                }
            } else {
                $errors = $result->getErrorMessagesIndexedByProperty();
            }
        }

        return $this->renderView('admin/rbac/update', [
            'form' => $form,
            'data' => UpdateViewData::create($itemType, $form, $availableChildren, $users, $errors, $this->url, $this->translator()),
        ]);
    }

    private function createForm(string $itemType): AuthItemForm
    {
        return new AuthItemForm($this->translator, $itemType);
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

    /**
     * @return array<string, Item>
     */
    private function getAvailableChildren(string $itemType, string $excludeName = ''): array
    {
        $candidates = $itemType === 'role'
            ? $this->itemsStorage->getAll()
            : $this->itemsStorage->getPermissions();

        unset($candidates[$excludeName]);
        ksort($candidates);

        return $candidates;
    }

    private function loadForm(AuthItemForm $form, string $name, string $description, string $rule, ?array $children): void
    {
        $form->name = $name;
        $form->description = $description;
        $form->rule = $rule !== '' ? $rule : $form->rule;

        if (is_array($children)) {
            $form->children = array_values(array_filter($children, 'is_string'));
        }
    }

    private function processUserAssignments(array $assignedUserIds, string $itemName): void
    {
        $submittedIds = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($assignedUserIds as $id) {
            if (is_string($id) && $id !== '') {
                $submittedIds[$id] = $id;
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
}
