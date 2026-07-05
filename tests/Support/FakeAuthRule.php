<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\RuleContext;
use Yiisoft\Rbac\RuleInterface;

final class FakeAuthRule implements RuleInterface
{
    #[\Override]
    public function execute(?string $userId, Item $item, RuleContext $context): bool
    {
        return true;
    }
}
