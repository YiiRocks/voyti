<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Http\Method;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\Role;

/**
 * Covers only the branches of AbstractAuthItemController that differ for roles
 * (addRole/updateRole/removeRole/getRoles, and the "assign a new user" loop in
 * processUserAssignments, which is exercised here rather than in
 * PermissionControllerTest because direct permission-to-user assignment is
 * disabled by default in Yiisoft\Rbac\Manager). Branches shared verbatim with
 * permissions (invalid-form handling, item-not-found, filtering) are already
 * covered there and are not repeated here.
 */
final class RoleControllerTest extends TestCase
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

    public function testCreateAddsRoleWithRuleAndChildren(): void
    {
        $this->harness->seedRbacRole('child-role');

        $response = $this->harness->roleController->create($this->harness->request(
            Method::POST,
            [
                'role' => [
                    'name' => 'manager',
                    'description' => 'Manages the team',
                    'rule' => 'IsTeamLeadRule',
                    'children' => ['child-role'],
                ],
            ],
        ));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/voyti/roles', $response->getHeaderLine('Location'));

        $role = $this->harness->rbacManager->getRole('manager');
        $this->assertNotNull($role);
        $this->assertSame('Manages the team', $role->getDescription());
        $this->assertSame('IsTeamLeadRule', $role->getRuleName());
        $this->assertArrayHasKey('child-role', $this->harness->rbacItemsStorage->getDirectChildren('manager'));
    }

    public function testDeleteRemovesRoleAndChildren(): void
    {
        $this->harness->seedRbacRole('child-role');
        $this->harness->rbacItemsStorage->add(new Role('manager'));
        $this->harness->rbacManager->addChild('manager', 'child-role');

        $response = $this->harness->roleController->delete('manager');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNull($this->harness->rbacManager->getRole('manager'));
    }

    public function testIndexListsRoleItems(): void
    {
        $this->harness->seedRbacRole('manager');
        $this->harness->seedRbacRole('editor');

        $response = $this->harness->roleController->index($this->harness->request(Method::GET));
        $html = $this->harness->responseBody($response);

        $this->assertStringContainsString('manager', $html);
        $this->assertStringContainsString('editor', $html);
    }

    public function testIndexShowsDirectChildRoleNames(): void
    {
        $this->harness->seedRbacRole('editor');
        $this->harness->rbacItemsStorage->add(new Role('manager'));
        $this->harness->rbacManager->addChild('manager', 'editor');

        $response = $this->harness->roleController->index($this->harness->request(Method::GET));
        $html = $this->harness->responseBody($response);

        $this->assertStringContainsString(
            '<div class="col-3 text-break">manager</div><div class="col-4 text-break"></div><div class="col-2 text-break">editor</div>',
            $html,
        );
    }

    public function testUpdateRenamesRoleAndAssignsNewlyCheckedUser(): void
    {
        $this->harness->rbacItemsStorage->add(new Role('manager'));
        $alice = $this->createUser('alice', 'alice@example.test');
        $bob = $this->createUser('bob', 'bob@example.test');
        $this->harness->rbacAssignmentsStorage->add(new Assignment((string) $alice->getId(), 'manager', time()));

        $response = $this->harness->roleController->update(
            $this->harness->request(
                Method::POST,
                [
                    'role' => [
                        'name' => 'team-lead',
                        'description' => 'Leads the team',
                        'rule' => 'IsTeamLeadRule',
                        'children' => [],
                    ],
                    'assignedUsers' => [(string) $alice->getId(), (string) $bob->getId()],
                ],
            ),
            'manager',
        );

        $this->assertSame(302, $response->getStatusCode());

        $renamed = $this->harness->rbacManager->getRole('team-lead');
        $this->assertNotNull($renamed);
        $this->assertSame('IsTeamLeadRule', $renamed->getRuleName());
        $this->assertNull($this->harness->rbacManager->getRole('manager'));

        $assignedIds = array_map(
            static fn (Assignment $assignment): string => $assignment->getUserId(),
            $this->harness->rbacAssignmentsStorage->getByItemNames(['team-lead']),
        );
        sort($assignedIds);
        $expectedIds = [(string) $alice->getId(), (string) $bob->getId()];
        sort($expectedIds);
        $this->assertSame($expectedIds, $assignedIds);
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
