<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

final class M000002CreateProfileTable implements RevertibleMigrationInterface
{

    #[\Override]
    public function down(MigrationBuilder $b): void
    {
        $b->dropForeignKey('{{%user_profile}}', 'fk_user_profile_user');
        $b->dropPrimaryKey('{{%user_profile}}', 'pk_user_profile_user_id');
        $b->dropTable('{{%user_profile}}');
    }
    #[\Override]
    public function up(MigrationBuilder $b): void
    {
        $c = $b->columnBuilder();
        $b->createTable('{{%user_profile}}', [
            'user_id' => $c::integer()->notNull(),
            'name' => $c::string(255),
            'public_email' => $c::string(255),
            'gravatar_email' => $c::string(255),
            'gravatar_id' => $c::string(32),
            'location' => $c::string(255),
            'website' => $c::string(255),
            'bio' => $c::text(),
            'timezone' => $c::string(40),
        ]);
        $b->addPrimaryKey('{{%user_profile}}', 'pk_user_profile_user_id', 'user_id');
        $b->addForeignKey('{{%user_profile}}', 'fk_user_profile_user', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
    }
}
