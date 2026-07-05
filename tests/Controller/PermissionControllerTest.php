<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use YiiRocks\Voyti\Controller\PermissionController;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Http\Method;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidationContext;
use Yiisoft\Validator\ValidatorInterface;

final class PermissionControllerTest extends TestCase
{
    private ?ConnectionInterface $db = null;
    private ControllerHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->getDb();
        ConnectionProvider::set($this->db);
        $this->createSchema($this->db);

        $this->harness = new ControllerHarness(dirname(__DIR__, 2));
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->dropSchema($this->db);
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testCreateAddsPermissionWithRuleAndChildren(): void
    {
        $this->harness->seedRbacPermission('child-permission');

        $response = $this->harness->permissionController->create($this->harness->request(
            Method::POST,
            [
                'permission' => [
                    'name' => 'manage-articles',
                    'description' => 'Manage articles',
                    'rule' => 'IsAuthorRule',
                    'children' => ['child-permission'],
                ],
            ],
        ));

        $this->assertSame(302, $response->getStatusCode());

        $permission = $this->harness->rbacManager->getPermission('manage-articles');
        $this->assertNotNull($permission);
        $this->assertSame('Manage articles', $permission->getDescription());
        $this->assertSame('IsAuthorRule', $permission->getRuleName());
        $this->assertArrayHasKey(
            'child-permission',
            $this->harness->rbacItemsStorage->getDirectChildren('manage-articles'),
        );
    }

    public function testCreatePostWithoutParsedBodyIsTreatedAsEmpty(): void
    {
        $response = $this->harness->permissionController->create($this->harness->request(Method::POST));

        $this->assertSame(302, $response->getStatusCode());
    }

    public function testCreateWithInvalidFormRendersErrorsAndDoesNotCreatePermission(): void
    {
        $controller = $this->buildPermissionControllerWithValidator(
            new RejectingAuthItemValidatorStub('Name is invalid.'),
        );

        $response = $controller->create($this->harness->request(
            Method::POST,
            ['permission' => ['name' => '', 'description' => '', 'rule' => '', 'children' => []]],
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Name is invalid.', $this->harness->responseBody($response));
        $this->assertNull($this->harness->rbacManager->getPermission(''));
    }

    public function testDeleteCallsRemovePermissionNotRemoveRole(): void
    {
        $spyManager = new RemovalTrackingManagerStub();
        $controller = new PermissionController(
            $this->harness->translator,
            $this->harness->webViewRenderer,
            $this->harness->url,
            new RejectingAuthItemValidatorStub('unused'),
            new \Nyholm\Psr7\Factory\Psr17Factory(),
            $this->harness->users,
            $this->harness->rbacItemsStorage,
            $spyManager,
            $this->harness->rbacAssignmentsStorage,
            $this->harness->flash,
        );

        $controller->delete('manage-articles');

        $this->assertSame(['manage-articles'], $spyManager->removedPermissionNames);
        $this->assertSame([], $spyManager->removedRoleNames);
    }

    public function testDeleteRemovesPermissionAndChildren(): void
    {
        $this->harness->seedRbacPermission('child-permission');
        $this->harness->rbacItemsStorage->add(new Permission('manage-articles'));
        $this->harness->rbacManager->addChild('manage-articles', 'child-permission');

        $response = $this->harness->permissionController->delete('manage-articles');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/voyti/permissions', $response->getHeaderLine('Location'));
        $this->assertNull($this->harness->rbacManager->getPermission('manage-articles'));
    }

    public function testIndexFiltersByNameAndDescription(): void
    {
        $this->harness->rbacItemsStorage->add(
            (new Permission('view-orders'))->withDescription('View customer orders'),
        );
        $this->harness->rbacItemsStorage->add(
            (new Permission('edit-articles'))->withDescription('Edit blog articles'),
        );

        $byName = $this->harness->permissionController->index(
            $this->harness->request(Method::GET, [], ['name' => 'view']),
        );
        $htmlByName = $this->harness->responseBody($byName);
        $this->assertStringContainsString('view-orders', $htmlByName);
        $this->assertStringNotContainsString('edit-articles', $htmlByName);

        $byDescription = $this->harness->permissionController->index(
            $this->harness->request(Method::GET, [], ['description' => 'blog']),
        );
        $htmlByDescription = $this->harness->responseBody($byDescription);
        $this->assertStringContainsString('edit-articles', $htmlByDescription);
        $this->assertStringNotContainsString('view-orders', $htmlByDescription);
    }

    public function testUpdateFiltersOutNonStringChildrenEntries(): void
    {
        $this->harness->seedRbacPermission('valid-child');
        $this->harness->rbacItemsStorage->add(new Permission('manage-articles'));

        $response = $this->harness->permissionController->update(
            $this->harness->request(
                Method::POST,
                [
                    'permission' => [
                        'name' => 'manage-articles',
                        'description' => '',
                        'rule' => '',
                        'children' => ['valid-child', 123, null],
                    ],
                ],
            ),
            'manage-articles',
        );

        $this->assertSame(302, $response->getStatusCode());
        $children = $this->harness->rbacItemsStorage->getDirectChildren('manage-articles');
        $this->assertSame(['valid-child'], array_keys($children));
    }

    public function testUpdateIgnoresNonStringAndEmptyAssignedUserEntries(): void
    {
        $this->harness->rbacItemsStorage->add(new Permission('manage-articles'));
        $alice = $this->createUser('alice', 'alice@example.test');
        $this->harness->rbacAssignmentsStorage->add(new Assignment((string) $alice->getId(), 'manage-articles', time()));

        $response = $this->harness->permissionController->update(
            $this->harness->request(
                Method::POST,
                [
                    'permission' => ['name' => 'manage-articles', 'description' => '', 'rule' => '', 'children' => []],
                    'assignedUsers' => [(string) $alice->getId(), '', 999],
                ],
            ),
            'manage-articles',
        );

        $this->assertSame(302, $response->getStatusCode());
        $assignedIds = array_map(
            static fn (Assignment $assignment): string => $assignment->getUserId(),
            $this->harness->rbacAssignmentsStorage->getByItemNames(['manage-articles']),
        );
        $this->assertSame([(string) $alice->getId()], $assignedIds);
    }

    public function testUpdateKeepsExistingRuleWhenFormOmitsIt(): void
    {
        $this->harness->rbacItemsStorage->add((new Permission('manage-articles'))->withRuleName('ExistingRule'));

        $html = $this->harness->responseBody($this->harness->permissionController->update(
            $this->harness->request(Method::GET),
            'manage-articles',
        ));

        $this->assertStringContainsString('ExistingRule', $html);
    }

    public function testUpdateRenamesDescriptionRuleAndChildren(): void
    {
        $this->harness->seedRbacPermission('old-child');
        $this->harness->seedRbacPermission('new-child');
        $this->harness->rbacItemsStorage->add(new Permission('manage-articles'));
        $this->harness->rbacManager->addChild('manage-articles', 'old-child');

        $response = $this->harness->permissionController->update(
            $this->harness->request(
                Method::POST,
                [
                    'permission' => [
                        'name' => 'manage-posts',
                        'description' => 'Updated description',
                        'rule' => 'PostOwnerRule',
                        'children' => ['new-child'],
                    ],
                ],
            ),
            'manage-articles',
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/voyti/permissions', $response->getHeaderLine('Location'));

        $renamed = $this->harness->rbacManager->getPermission('manage-posts');
        $this->assertNotNull($renamed);
        $this->assertNull($this->harness->rbacManager->getPermission('manage-articles'));
        $this->assertSame('Updated description', $renamed->getDescription());
        $this->assertSame('PostOwnerRule', $renamed->getRuleName());

        $children = $this->harness->rbacItemsStorage->getDirectChildren('manage-posts');
        $this->assertArrayHasKey('new-child', $children);
        $this->assertArrayNotHasKey('old-child', $children);
    }

    public function testUpdateShowsOnlyAssignedUsersAndRemovesUncheckedOnesOnSubmit(): void
    {
        $this->harness->rbacItemsStorage->add(new Permission('manage-articles'));
        $alice = $this->createUser('alice', 'alice@example.test');
        $bob = $this->createUser('bob', 'bob@example.test');
        $this->createUser('carol', 'carol@example.test');
        $this->harness->rbacAssignmentsStorage->add(new Assignment((string) $alice->getId(), 'manage-articles', time()));
        $this->harness->rbacAssignmentsStorage->add(new Assignment((string) $bob->getId(), 'manage-articles', time()));

        $viewResponse = $this->harness->permissionController->update(
            $this->harness->request(Method::GET),
            'manage-articles',
        );
        $html = $this->harness->responseBody($viewResponse);

        $this->assertStringContainsString('alice', $html);
        $this->assertStringContainsString('bob', $html);
        $this->assertStringNotContainsString('carol', $html);
        $this->assertStringNotContainsString('Available', $html);
        $this->assertMatchesRegularExpression(
            '/name="assignedUsers\[\]" value="' . $alice->getId() . '"[^>]*checked/',
            $html,
        );

        $submitResponse = $this->harness->permissionController->update(
            $this->harness->request(
                Method::POST,
                [
                    'permission' => [
                        'name' => 'manage-articles',
                        'description' => '',
                        'rule' => '',
                        'children' => [],
                    ],
                    'assignedUsers' => [(string) $alice->getId()],
                ],
            ),
            'manage-articles',
        );
        $this->assertSame(302, $submitResponse->getStatusCode());

        $remainingIds = array_map(
            static fn (Assignment $assignment): string => $assignment->getUserId(),
            $this->harness->rbacAssignmentsStorage->getByItemNames(['manage-articles']),
        );
        $this->assertSame([(string) $alice->getId()], $remainingIds);
    }

    public function testUpdateWithInvalidFormRendersErrorsAndDoesNotChangePermission(): void
    {
        $this->harness->rbacItemsStorage->add((new Permission('manage-articles'))->withDescription('Original'));

        $controller = $this->buildPermissionControllerWithValidator(
            new RejectingAuthItemValidatorStub('Update rejected.'),
        );

        $response = $controller->update(
            $this->harness->request(
                Method::POST,
                ['permission' => ['name' => 'manage-articles', 'description' => 'Changed', 'rule' => '', 'children' => []]],
            ),
            'manage-articles',
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Update rejected.', $this->harness->responseBody($response));
        $permission = $this->harness->rbacManager->getPermission('manage-articles');
        $this->assertNotNull($permission);
        $this->assertSame('Original', $permission->getDescription());
    }

    public function testUpdateWithNonStringRuleFieldIgnoresIt(): void
    {
        $this->harness->rbacItemsStorage->add((new Permission('manage-articles'))->withRuleName('ExistingRule'));

        $response = $this->harness->permissionController->update(
            $this->harness->request(
                Method::POST,
                ['permission' => ['name' => 'manage-articles', 'description' => '', 'rule' => 123]],
            ),
            'manage-articles',
        );

        $this->assertSame(302, $response->getStatusCode());
        $permission = $this->harness->rbacManager->getPermission('manage-articles');
        $this->assertNotNull($permission);
        $this->assertSame('ExistingRule', $permission->getRuleName());
    }

    public function testUpdateWithoutChildrenFieldPreservesExistingChildren(): void
    {
        $this->harness->seedRbacPermission('existing-child');
        $this->harness->rbacItemsStorage->add(new Permission('manage-articles'));
        $this->harness->rbacManager->addChild('manage-articles', 'existing-child');

        $response = $this->harness->permissionController->update(
            $this->harness->request(
                Method::POST,
                ['permission' => ['name' => 'manage-articles', 'description' => '', 'rule' => '']],
            ),
            'manage-articles',
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertArrayHasKey(
            'existing-child',
            $this->harness->rbacItemsStorage->getDirectChildren('manage-articles'),
        );
    }

    public function testUpdateWithUnknownNameRendersError(): void
    {
        $response = $this->harness->permissionController->update(
            $this->harness->request(Method::GET),
            'missing-permission',
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    private function buildPermissionControllerWithValidator(ValidatorInterface $validator): PermissionController
    {
        return new PermissionController(
            $this->harness->translator,
            $this->harness->webViewRenderer,
            $this->harness->url,
            $validator,
            new \Nyholm\Psr7\Factory\Psr17Factory(),
            $this->harness->users,
            $this->harness->rbacItemsStorage,
            $this->harness->rbacManager,
            $this->harness->rbacAssignmentsStorage,
            $this->harness->flash,
        );
    }

    private function createSchema(ConnectionInterface $db): void
    {
        $db->createCommand('CREATE TABLE IF NOT EXISTS {{%user}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            auth_key VARCHAR(32) NOT NULL,
            auth_tf_enabled INTEGER NOT NULL DEFAULT 0,
            auth_tf_key VARCHAR(64),
            auth_tf_type VARCHAR(20),
            blocked_at INTEGER,
            confirmed_at INTEGER,
            created_at INTEGER NOT NULL,
            flags INTEGER NOT NULL DEFAULT 0,
            gdpr_consent INTEGER NOT NULL DEFAULT 0,
            gdpr_consent_date INTEGER,
            gdpr_deleted INTEGER NOT NULL DEFAULT 0,
            last_login_at INTEGER,
            last_login_ip VARCHAR(45),
            password_changed_at INTEGER,
            registration_ip VARCHAR(45),
            unconfirmed_email VARCHAR(255),
            updated_at INTEGER NOT NULL
        )')->execute();
    }

    private function createUser(string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey(bin2hex(random_bytes(16)));
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }

    private function dropSchema(ConnectionInterface $db): void
    {
        $db->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
    }
}

final class RejectingAuthItemValidatorStub implements ValidatorInterface
{
    public function __construct(private readonly string $message)
    {
    }

    #[\Override]
    public function validate(
        mixed $data,
        callable|iterable|object|string|null $rules = null,
        ?ValidationContext $context = null,
    ): Result {
        $result = new Result();
        $result->addError($this->message);

        return $result;
    }
}

/**
 * A minimal spy implementation of ManagerInterface that only records removeRole()/
 * removePermission() calls; every other method is unused by AbstractAuthItemController::delete()
 * and throws if invoked. Needed because the real Manager funnels both calls through the same
 * private removeItem() helper, so state-based assertions can't tell them apart.
 */
final class RemovalTrackingManagerStub implements ManagerInterface
{
    /** @var string[] */
    public array $removedPermissionNames = [];

    /** @var string[] */
    public array $removedRoleNames = [];

    public function addChild(string $parentName, string $childName): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function addPermission(Permission $permission): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function addRole(Role $role): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function assign(string $itemName, int|\Stringable|string $userId, ?int $createdAt = null): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function canAddChild(string $parentName, string $childName): bool
    {
        throw new \LogicException('Not implemented.');
    }

    public function getChildRoles(string $roleName): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getDefaultRoleNames(): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getDefaultRoles(): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getGuestRole(): ?Role
    {
        throw new \LogicException('Not implemented.');
    }

    public function getGuestRoleName(): ?string
    {
        throw new \LogicException('Not implemented.');
    }

    public function getItemsByUserId(int|\Stringable|string $userId): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getPermission(string $name): ?Permission
    {
        throw new \LogicException('Not implemented.');
    }

    public function getPermissionsByRoleName(string $roleName): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getPermissionsByUserId(int|\Stringable|string $userId): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getRole(string $name): ?Role
    {
        throw new \LogicException('Not implemented.');
    }

    public function getRolesByUserId(int|\Stringable|string $userId): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getUserIdsByRoleName(string $roleName): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function hasChild(string $parentName, string $childName): bool
    {
        throw new \LogicException('Not implemented.');
    }

    public function hasChildren(string $parentName): bool
    {
        throw new \LogicException('Not implemented.');
    }

    public function removeChild(string $parentName, string $childName): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function removeChildren(string $parentName): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function removePermission(string $name): self
    {
        $this->removedPermissionNames[] = $name;

        return $this;
    }

    public function removeRole(string $name): self
    {
        $this->removedRoleNames[] = $name;

        return $this;
    }

    public function revoke(string $itemName, int|\Stringable|string $userId): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function revokeAll(int|\Stringable|string $userId): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function setDefaultRoleNames(array|\Closure $roleNames): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function setGuestRoleName(?string $name): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function updatePermission(string $name, Permission $permission): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function updateRole(string $name, Role $role): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function userHasPermission(int|string|\Stringable|null $userId, string $permissionName, array $parameters = []): bool
    {
        throw new \LogicException('Not implemented.');
    }
}
