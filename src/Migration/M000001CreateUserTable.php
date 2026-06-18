<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

final class M000001CreateUserTable implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $c = $b->columnBuilder();
        $b->createTable('{{%user}}', [
            'id' => $c::primaryKey(),
            'username' => $c::string(255)->notNull(),
            'email' => $c::string(255)->notNull(),
            'password_hash' => $c::string(60)->notNull(),
            'auth_key' => $c::string(32)->notNull(),
            'unconfirmed_email' => $c::string(255),
            'registration_ip' => $c::string(45),
            'last_login_ip' => $c::string(45),
            'flags' => $c::integer()->notNull()->defaultValue(0),
            'confirmed_at' => $c::integer(),
            'blocked_at' => $c::integer(),
            'password_changed_at' => $c::integer(),
            'last_login_at' => $c::integer(),
            'auth_tf_key' => $c::string(32),
            'auth_tf_enabled' => $c::boolean()->defaultValue(false),
            'auth_tf_type' => $c::string(20),
            'auth_tf_mobile_phone' => $c::string(20),
            'gdpr_deleted' => $c::boolean()->defaultValue(false),
            'gdpr_consent' => $c::boolean()->defaultValue(false),
            'gdpr_consent_date' => $c::integer(),
            'updated_at' => $c::integer()->notNull(),
            'created_at' => $c::integer()->notNull(),
        ]);
        $b->createIndex('{{%user}}', 'idx_user_username', 'username', 'UNIQUE');
        $b->createIndex('{{%user}}', 'idx_user_email', 'email', 'UNIQUE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropIndex('{{%user}}', 'idx_user_email');
        $b->dropIndex('{{%user}}', 'idx_user_username');
        $b->dropTable('{{%user}}');
    }
}
