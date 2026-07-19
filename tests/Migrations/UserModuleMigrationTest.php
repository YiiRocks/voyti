<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Migrations;

use M240118192500CreateAssignmentsTable;
use M240118192500CreateItemsTables;
use M260621101843_create_user_module_tables;
use ReflectionMethod;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Security\PasswordHasher;

require_once dirname(__DIR__, 2) . '/vendor/yiisoft/rbac-db/migrations/items/M240118192500CreateItemsTables.php';
require_once dirname(__DIR__, 2) . '/vendor/yiisoft/rbac-db/migrations/assignments/M240118192500CreateAssignmentsTable.php';
require_once dirname(__DIR__, 2) . '/migrations/M260621101843_create_user_module_tables.php';

final class UserModuleMigrationTest extends TestCase
{
    private MigrationBuilder $builder;
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createSqliteConnection();
        $this->builder = new MigrationBuilder($this->connection, new NullMigrationInformer());
        (new M240118192500CreateItemsTables())->up($this->builder);
        (new M240118192500CreateAssignmentsTable())->up($this->builder);
    }

    public function testMigrationsRunSuccessfullyOnSqliteAndSeedDefaultAdmin(): void
    {
        $hasher = TestPasswordHasherFactory::create();
        $output = $this->runMigration($hasher);

        preg_match('/password:\s*(\S+)/', $output, $matches);
        self::assertNotEmpty($matches[1] ?? null, 'seed output should include a generated password');

        $user = $this->fetchOne(
            'SELECT * FROM {{%user}} WHERE email = :email',
            ['email' => M260621101843_create_user_module_tables::SEED_EMAIL],
        );
        self::assertNotNull($user);
        self::assertTrue($hasher->validate($matches[1], $user['password_hash']));
        self::assertNotSame(0, (int) $user['confirmed_at']);

        self::assertNotNull($this->fetchOne('SELECT * FROM {{%user_profile}} WHERE user_id = :id', ['id' => $user['id']]));

        $permission = $this->fetchOne("SELECT * FROM {{%yii_rbac_item}} WHERE name = 'voyti-admin-dashboard'");
        self::assertNotNull($permission);
        self::assertSame('permission', $permission['type']);

        $role = $this->fetchOne("SELECT * FROM {{%yii_rbac_item}} WHERE name = 'administrator'");
        self::assertNotNull($role);
        self::assertSame('role', $role['type']);

        self::assertNotNull(
            $this->fetchOne("SELECT * FROM {{%yii_rbac_item_child}} WHERE parent = 'administrator' AND child = 'voyti-admin-dashboard'"),
        );

        $assignment = $this->fetchOne("SELECT * FROM {{%yii_rbac_assignment}} WHERE item_name = 'administrator'");
        self::assertNotNull($assignment);
        self::assertSame((string) $user['id'], (string) $assignment['user_id']);
    }

    public function testSeedDefaultAdminIsIdempotent(): void
    {
        $migration = new M260621101843_create_user_module_tables(new ModuleConfig(), TestPasswordHasherFactory::create());
        ob_start();
        $migration->up($this->builder);
        ob_get_clean();

        $seedMethod = new ReflectionMethod($migration, 'seedDefaultAdmin');
        ob_start();
        $seedMethod->invoke($migration, $this->builder);
        ob_get_clean();

        self::assertSame(1, $this->userCount());
    }

    public function testSeedDefaultAdminReusesExistingChildLink(): void
    {
        $this->insertRbacItem('voyti-admin-dashboard', 'permission');
        $this->insertRbacItem('administrator', 'role');
        $this->connection->createCommand()->insert('{{%yii_rbac_item_child}}', [
            'parent' => 'administrator',
            'child' => 'voyti-admin-dashboard',
        ])->execute();

        $this->runMigration();

        self::assertSame(1, $this->userCount());
        $childCount = (int) $this->connection->createCommand(
            "SELECT COUNT(*) FROM {{%yii_rbac_item_child}} WHERE parent = 'administrator' AND child = 'voyti-admin-dashboard'",
        )->queryScalar();
        self::assertSame(1, $childCount);
    }

    public function testSeedDefaultAdminReusesExistingPermissionItem(): void
    {
        $this->insertRbacItem('voyti-admin-dashboard', 'permission');

        $this->runMigration();

        self::assertSame(1, $this->userCount());
        $permissionCount = (int) $this->connection->createCommand(
            "SELECT COUNT(*) FROM {{%yii_rbac_item}} WHERE name = 'voyti-admin-dashboard'",
        )->queryScalar();
        self::assertSame(1, $permissionCount);
        self::assertNotNull($this->fetchOne("SELECT * FROM {{%yii_rbac_assignment}} WHERE item_name = 'administrator'"));
    }

    public function testSeedDefaultAdminReusesExistingRoleItem(): void
    {
        $this->insertRbacItem('administrator', 'role');

        $this->runMigration();

        self::assertSame(1, $this->userCount());
        $roleCount = (int) $this->connection->createCommand(
            "SELECT COUNT(*) FROM {{%yii_rbac_item}} WHERE name = 'administrator'",
        )->queryScalar();
        self::assertSame(1, $roleCount);
        self::assertNotNull($this->fetchOne("SELECT * FROM {{%yii_rbac_assignment}} WHERE item_name = 'administrator'"));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function fetchOne(string $sql, array $params = []): ?array
    {
        return $this->connection->createCommand($sql, $params)->queryOne();
    }

    private function insertRbacItem(string $name, string $type): void
    {
        $this->connection->createCommand()->insert('{{%yii_rbac_item}}', [
            'name' => $name,
            'type' => $type,
            'created_at' => time(),
            'updated_at' => time(),
        ])->execute();
    }

    private function runMigration(?PasswordHasher $hasher = null): string
    {
        $migration = new M260621101843_create_user_module_tables(new ModuleConfig(), $hasher ?? TestPasswordHasherFactory::create());
        ob_start();
        $migration->up($this->builder);
        return (string) ob_get_clean();
    }

    private function userCount(): int
    {
        return (int) $this->connection->createCommand('SELECT COUNT(*) FROM {{%user}}')->queryScalar();
    }
}
