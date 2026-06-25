<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

final class M000004CreateTokenTable implements RevertibleMigrationInterface
{

    #[\Override]
    public function down(MigrationBuilder $b): void
    {
        $b->dropForeignKey('{{%user_token}}', 'fk_user_token_user');
        $b->dropPrimaryKey('{{%user_token}}', 'pk_user_token');
        $b->dropTable('{{%user_token}}');
    }
    #[\Override]
    public function up(MigrationBuilder $b): void
    {
        $c = $b->columnBuilder();
        $b->createTable('{{%user_token}}', [
            'user_id' => $c::integer()->notNull(),
            'code' => $c::string(32)->notNull(),
            'type' => $c::smallint()->notNull(),
            'created_at' => $c::integer()->notNull(),
        ]);
        $b->addPrimaryKey('{{%user_token}}', 'pk_user_token', ['user_id', 'code', 'type']);
        $b->addForeignKey('{{%user_token}}', 'fk_user_token_user', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
    }
}
