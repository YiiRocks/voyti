<?php

declare(strict_types=1);

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

final class M260621101843_create_user_module_tables implements RevertibleMigrationInterface
{

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%user_session_history}}');
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
            'gdpr_deleted' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
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
            'gravatar_email' => ColumnBuilder::string(255),
            'location' => ColumnBuilder::string(255),
            'name' => ColumnBuilder::string(255),
            'public_email' => ColumnBuilder::string(255),
            'timezone' => ColumnBuilder::string(40),
            'website' => ColumnBuilder::string(255),
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
        ]);

        $b->createTable('{{%user_token}}', [
            'user_id' => ColumnBuilder::integer()->notNull(),
            'code' => ColumnBuilder::string(32)->notNull(),
            'type' => ColumnBuilder::smallint()->notNull(),
            'created_at' => ColumnBuilder::integer()->notNull(),
        ]);

        $b->createTable('{{%user_session_history}}', [
            'user_id' => ColumnBuilder::integer()->notNull(),
            'session_id' => ColumnBuilder::string(255)->notNull(),
            'user_agent' => ColumnBuilder::text(),
            'ip' => ColumnBuilder::string(45)->notNull(),
            'created_at' => ColumnBuilder::integer()->notNull(),
            'updated_at' => ColumnBuilder::integer()->notNull(),
        ]);

        $b->addPrimaryKey('{{%user_token}}', 'pk-user-token-user-id-code-type', ['user_id', 'code', 'type']);
        $b->addPrimaryKey('{{%user_session_history}}', 'pk-user-session-history-user-id-session-id', ['user_id', 'session_id']);

        $b->createIndex('{{%user}}', 'idx-user-email', ['email'], 'UNIQUE');
        $b->createIndex('{{%user}}', 'idx-user-username', ['username'], 'UNIQUE');
        $b->createIndex('{{%user_profile}}', 'idx-user-profile-user-id', ['user_id'], 'UNIQUE');
        $b->createIndex('{{%user_social_account}}', 'idx-user-social-account-user-id', ['user_id']);
        $b->createIndex('{{%user_social_account}}', 'idx-user-social-account-provider-client-id', ['provider', 'client_id'], 'UNIQUE');
        $b->createIndex('{{%user_social_account}}', 'idx-user-social-account-code', ['code'], 'UNIQUE');
        $b->createIndex('{{%user_token}}', 'idx-user-token-user-id', ['user_id']);
        $b->createIndex('{{%user_session_history}}', 'idx-user-session-history-user-id', ['user_id']);
        $b->createIndex('{{%user_session_history}}', 'idx-user-session-history-session-id', ['session_id']);
        $b->createIndex('{{%user_session_history}}', 'idx-user-session-history-updated-at', ['updated_at']);

        $b->addForeignKey('{{%user_profile}}', 'fk-user-profile-user-id', ['user_id'], '{{%user}}', ['id'], 'CASCADE', 'RESTRICT');
        $b->addForeignKey('{{%user_social_account}}', 'fk-user-social-account-user-id', ['user_id'], '{{%user}}', ['id'], 'CASCADE', 'RESTRICT');
        $b->addForeignKey('{{%user_token}}', 'fk-user-token-user-id', ['user_id'], '{{%user}}', ['id'], 'CASCADE', 'RESTRICT');
        $b->addForeignKey('{{%user_session_history}}', 'fk-user-session-history-user-id', ['user_id'], '{{%user}}', ['id'], 'CASCADE', 'RESTRICT');
    }
}
