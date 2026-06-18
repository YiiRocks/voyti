<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\SessionHistory;

use Yiisoft\ActiveRecord\ActiveRecordFactory;

final class TerminateUserSessionsService
{
    public function __construct(
        private readonly ActiveRecordFactory $arFactory,
    ) {
    }

    public function run(int $userId): void
    {
        $query = $this->arFactory->createQuery(\YiiRocks\Voyti\Entity\SessionHistory::class);
        $query->where(['user_id' => $userId])->delete();
    }
}
