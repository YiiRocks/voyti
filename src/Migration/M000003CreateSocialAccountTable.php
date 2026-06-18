<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

final class M000003CreateSocialAccountTable implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $c = $b->columnBuilder();
        $b->createTable('{{%social_account}}', [
            'id' => $c::primaryKey(),
            'user_id' => $c::integer(),
            'provider' => $c::string(255)->notNull(),
            'client_id' => $c::string(255)->notNull(),
            'data' => $c::text(),
            'code' => $c::string(32),
            'email' => $c::string(255),
            'username' => $c::string(255),
            'created_at' => $c::integer()->notNull(),
        ]);
        $b->addForeignKey('{{%social_account}}', 'fk_social_account_user', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropForeignKey('{{%social_account}}', 'fk_social_account_user');
        $b->dropTable('{{%social_account}}');
    }
}
