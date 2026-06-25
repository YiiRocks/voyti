<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

final class M000005CreateSessionHistoryTable implements RevertibleMigrationInterface
{

    #[\Override]
    public function down(MigrationBuilder $b): void
    {
        $b->dropForeignKey('{{%user_session_history}}', 'fk_user_session_history_user');
        $b->dropPrimaryKey('{{%user_session_history}}', 'pk_user_session_history');
        $b->dropTable('{{%user_session_history}}');
    }
    #[\Override]
    public function up(MigrationBuilder $b): void
    {
        $c = $b->columnBuilder();
        $b->createTable('{{%user_session_history}}', [
            'user_id' => $c::integer()->notNull(),
            'session_id' => $c::string(255)->notNull(),
            'user_agent' => $c::text(),
            'ip' => $c::string(45),
            'created_at' => $c::integer()->notNull(),
            'updated_at' => $c::integer()->notNull(),
        ]);
        $b->addPrimaryKey('{{%user_session_history}}', 'pk_user_session_history', ['user_id', 'session_id']);
        $b->addForeignKey('{{%user_session_history}}', 'fk_user_session_history_user', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
    }
}
