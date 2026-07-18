<?php

declare(strict_types=1);

use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;

final class M260621101843_create_user_module_tables implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public const ROLE_NAME = 'administrator';
    public const SEED_EMAIL = 'admin@example.com';
    public const SEED_USERNAME = 'admin';

    public function __construct(
        private readonly ModuleConfig $config,
        private readonly PasswordHasher $passwordHasher,
    ) {
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%user_audit_log}}');
        $b->dropTable('{{%user_password_history}}');
        $b->dropTable('{{%user_backup_code}}');
        $b->dropTable('{{%user_sessions}}');
        $b->dropTable('{{%user_token}}');
        $b->dropTable('{{%user_social_account}}');
        $b->dropTable('{{%user_profile}}');
        $b->dropTable('{{%user}}');
    }
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%user}}', [
            'id' => ColumnBuilder::primaryKey(),
            'username' => ColumnBuilder::string(255)->notNull(),
            'email' => ColumnBuilder::string(255)->notNull(),
            'password_hash' => ColumnBuilder::string(255)->notNull(),
            'auth_key' => ColumnBuilder::string(32)->notNull(),
            'auth_tf_enabled' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
            'auth_tf_key' => ColumnBuilder::string(64),
            'auth_tf_type' => ColumnBuilder::string(20),
            'blocked_at' => ColumnBuilder::integer(),
            'confirmed_at' => ColumnBuilder::integer(),
            'created_at' => ColumnBuilder::integer()->notNull(),
            'flags' => ColumnBuilder::integer()->notNull()->defaultValue(0),
            'gdpr_consent' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
            'gdpr_consent_date' => ColumnBuilder::integer(),
            'anonymized' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
            'last_login_at' => ColumnBuilder::integer(),
            'last_login_ip' => ColumnBuilder::string(45),
            'password_changed_at' => ColumnBuilder::integer(),
            'registration_ip' => ColumnBuilder::string(45),
            'unconfirmed_email' => ColumnBuilder::string(255),
            'updated_at' => ColumnBuilder::integer()->notNull(),
        ]);

        $b->createTable('{{%user_profile}}', [
            'user_id' => ColumnBuilder::integer()->notNull(),
            'bio' => ColumnBuilder::text(),
            'birthday' => ColumnBuilder::date(),
            'gravatar_email' => ColumnBuilder::string(255),
            'location' => ColumnBuilder::string(255),
            'name' => ColumnBuilder::string(255),
            'public_email' => ColumnBuilder::string(255),
            'timezone' => ColumnBuilder::string(40),
            'website' => ColumnBuilder::string(255),
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE ON UPDATE RESTRICT',
        ]);

        $b->createTable('{{%user_social_account}}', [
            'id' => ColumnBuilder::primaryKey(),
            'user_id' => ColumnBuilder::integer(),
            'provider' => ColumnBuilder::string(255)->notNull(),
            'client_id' => ColumnBuilder::string(255)->notNull(),
            'code' => ColumnBuilder::string(32),
            'email' => ColumnBuilder::string(255),
            'username' => ColumnBuilder::string(255),
            'data' => ColumnBuilder::text(),
            'created_at' => ColumnBuilder::integer()->notNull(),
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE ON UPDATE RESTRICT',
        ]);

        $b->createTable('{{%user_token}}', [
            'user_id' => ColumnBuilder::integer()->notNull(),
            'code' => ColumnBuilder::string(64)->notNull(),
            'type' => ColumnBuilder::smallint()->notNull(),
            'created_at' => ColumnBuilder::integer()->notNull(),
            'PRIMARY KEY ([[user_id]], [[code]], [[type]])',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE ON UPDATE RESTRICT',
        ]);

        $b->createTable('{{%user_sessions}}', [
            'user_id' => ColumnBuilder::integer()->notNull(),
            'session_id' => ColumnBuilder::string(255)->notNull(),
            'user_agent' => ColumnBuilder::text(),
            'ip' => ColumnBuilder::string(45)->notNull(),
            'created_at' => ColumnBuilder::integer()->notNull(),
            'updated_at' => ColumnBuilder::integer()->notNull(),
            'PRIMARY KEY ([[user_id]], [[session_id]])',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE ON UPDATE RESTRICT',
        ]);

        $b->createTable('{{%user_backup_code}}', [
            'user_id' => ColumnBuilder::integer()->notNull(),
            'code_hash' => ColumnBuilder::string(255)->notNull(),
            'used_at' => ColumnBuilder::integer(),
            'created_at' => ColumnBuilder::integer()->notNull(),
            'PRIMARY KEY ([[user_id]], [[code_hash]])',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE ON UPDATE RESTRICT',
        ]);

        $b->createTable('{{%user_password_history}}', [
            'user_id' => ColumnBuilder::integer()->notNull(),
            'password_hash' => ColumnBuilder::string(255)->notNull(),
            'created_at' => ColumnBuilder::integer()->notNull(),
            'PRIMARY KEY ([[user_id]], [[password_hash]])',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE ON UPDATE RESTRICT',
        ]);

        $b->createTable('{{%user_audit_log}}', [
            'id' => ColumnBuilder::primaryKey(),
            'actor_user_id' => ColumnBuilder::integer(),
            'target_user_id' => ColumnBuilder::integer(),
            'target_name' => ColumnBuilder::string(255),
            'action' => ColumnBuilder::string(64)->notNull(),
            'context' => ColumnBuilder::text(),
            'created_at' => ColumnBuilder::integer()->notNull(),
        ]);

        $b->createIndex('{{%user}}', 'idx-user-email', ['email'], 'UNIQUE');
        $b->createIndex('{{%user}}', 'idx-user-username', ['username'], 'UNIQUE');
        $b->createIndex('{{%user_profile}}', 'idx-user-profile-user-id', ['user_id'], 'UNIQUE');
        $b->createIndex('{{%user_social_account}}', 'idx-user-social-account-user-id', ['user_id']);
        $b->createIndex('{{%user_social_account}}', 'idx-user-social-account-provider-client-id', ['provider', 'client_id'], 'UNIQUE');
        $b->createIndex('{{%user_social_account}}', 'idx-user-social-account-code', ['code'], 'UNIQUE');
        $b->createIndex('{{%user_token}}', 'idx-user-token-user-id', ['user_id']);
        $b->createIndex('{{%user_sessions}}', 'idx-user-sessions-user-id', ['user_id']);
        $b->createIndex('{{%user_sessions}}', 'idx-user-sessions-session-id', ['session_id']);
        $b->createIndex('{{%user_sessions}}', 'idx-user-sessions-updated-at', ['updated_at']);
        $b->createIndex('{{%user_backup_code}}', 'idx-user-backup-code-user-id', ['user_id']);
        $b->createIndex('{{%user_password_history}}', 'idx-user-password-history-user-id', ['user_id']);
        $b->createIndex('{{%user_audit_log}}', 'idx-user-audit-log-actor-user-id', ['actor_user_id']);
        $b->createIndex('{{%user_audit_log}}', 'idx-user-audit-log-target-user-id', ['target_user_id']);
        $b->createIndex('{{%user_audit_log}}', 'idx-user-audit-log-created-at', ['created_at']);

        $this->seedDefaultAdmin($b);
    }

    private function rbacChildExists(MigrationBuilder $b, string $parent, string $child): bool
    {
        return (int) $b->getDb()->createCommand(
            'SELECT COUNT(*) FROM {{%yii_rbac_item_child}} WHERE parent = :parent AND child = :child',
            ['parent' => $parent, 'child' => $child],
        )->queryScalar() > 0;
    }

    private function rbacItemExists(MigrationBuilder $b, string $name): bool
    {
        return (int) $b->getDb()->createCommand(
            'SELECT COUNT(*) FROM {{%yii_rbac_item}} WHERE name = :name',
            ['name' => $name],
        )->queryScalar() > 0;
    }

    private function seedDefaultAdmin(MigrationBuilder $b): void
    {
        $userCount = (int) $b->getDb()->createCommand('SELECT COUNT(*) FROM {{%user}}')->queryScalar();
        if ($userCount > 0) {
            return;
        }

        $now = time();
        $password = Random::string(20);

        $b->insert('{{%user}}', [
            'username' => self::SEED_USERNAME,
            'email' => self::SEED_EMAIL,
            'password_hash' => $this->passwordHasher->hash($password),
            'auth_key' => Random::string(32),
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = $b->getDb()->getLastInsertId();
        $b->insert('{{%user_profile}}', ['user_id' => $userId]);

        $permissionName = $this->config->administratorPermissionName;

        if (!$this->rbacItemExists($b, $permissionName)) {
            $b->insert('{{%yii_rbac_item}}', [
                'name' => $permissionName,
                'type' => 'permission',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if (!$this->rbacItemExists($b, self::ROLE_NAME)) {
            $b->insert('{{%yii_rbac_item}}', [
                'name' => self::ROLE_NAME,
                'type' => 'role',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if (!$this->rbacChildExists($b, self::ROLE_NAME, $permissionName)) {
            $b->insert('{{%yii_rbac_item_child}}', [
                'parent' => self::ROLE_NAME,
                'child' => $permissionName,
            ]);
        }
        $b->insert('{{%yii_rbac_assignment}}', [
            'item_name' => self::ROLE_NAME,
            'user_id' => $userId,
            'created_at' => $now,
        ]);

        echo "\n==============================================\n";
        echo " Default admin account created:\n";
        echo "   username: : " . self::SEED_USERNAME . "\n";
        echo "   email:      " . self::SEED_EMAIL . "\n";
        echo "   password:   {$password}\n";
        echo " Change this email and password immediately after login.\n";
        echo "==============================================\n\n";
    }
}
