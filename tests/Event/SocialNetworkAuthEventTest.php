<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YiiRocks\Voyti\Event\Auth\SocialNetworkAuthEvent;

final class SocialNetworkAuthEventTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (!interface_exists('Yiisoft\Auth\Client\ClientInterface', false)) {
            eval('namespace Yiisoft\Auth\Client { interface ClientInterface {} }');
        }
    }

    public function testConstructAndGetters(): void
    {
        $account = (new ReflectionClass(\YiiRocks\Voyti\Entity\SocialNetworkAccount::class))->newInstanceWithoutConstructor();
        /** @psalm-suppress UndefinedClass, ArgumentTypeCoercion, InvalidArgument */
        $client = $this->createStub('Yiisoft\Auth\Client\ClientInterface');
        /** @psalm-suppress InvalidArgument */
        $event = new SocialNetworkAuthEvent($account, $client);

        $this->assertSame($account, $event->getAccount());
        $this->assertSame($client, $event->getClient());
    }
}
