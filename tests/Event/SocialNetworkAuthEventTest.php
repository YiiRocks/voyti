<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YiiRocks\Voyti\Event\SocialNetworkAuthEvent;

final class SocialNetworkAuthEventTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists('Yiisoft\Auth\Client\ClientInterface', false)) {
            eval('namespace Yiisoft\Auth\Client { interface ClientInterface {} }');
        }
    }

    public function testConstructAndGetters(): void
    {
        $account = (new ReflectionClass(\YiiRocks\Voyti\Entity\SocialNetworkAccount::class))->newInstanceWithoutConstructor();
        $client = $this->createStub('Yiisoft\Auth\Client\ClientInterface');
        $event = new SocialNetworkAuthEvent($account, $client);

        $this->assertSame($account, $event->getAccount());
        $this->assertSame($client, $event->getClient());
    }
}
