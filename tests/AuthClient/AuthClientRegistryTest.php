<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\AuthClientInterface;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class AuthClientRegistryTest extends TestCase
{

    public function testAllReturnsEmptyArrayWhenNoClients(): void
    {
        $registry = new AuthClientRegistry();
        self::assertSame([], $registry->all());
    }

    public function testAllReturnsListOfClients(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');

        $registry = new AuthClientRegistry($client);
        $all = $registry->all();
        self::assertIsArray($all);
        self::assertContainsOnlyInstancesOf(AuthClientInterface::class, $all);
        self::assertCount(1, $all);
    }

    public function testConstructWithClients(): void
    {
        $client1 = $this->createMock(AuthClientInterface::class);
        $client1->method('getName')->willReturn('client1');

        $client2 = $this->createMock(AuthClientInterface::class);
        $client2->method('getName')->willReturn('client2');

        $registry = new AuthClientRegistry($client1, $client2);
        self::assertCount(2, $registry->all());
    }

    public function testConstructWithDuplicateNameOverwrites(): void
    {
        $client1 = $this->createMock(AuthClientInterface::class);
        $client1->method('getName')->willReturn('github');

        $client2 = $this->createMock(AuthClientInterface::class);
        $client2->method('getName')->willReturn('github');

        $registry = new AuthClientRegistry($client1, $client2);
        $all = $registry->all();
        self::assertCount(1, $all);
        self::assertSame($client2, $all[0]);
    }
    public function testConstructWithNoClients(): void
    {
        $registry = new AuthClientRegistry();
        self::assertSame([], $registry->all());
    }

    public function testGetReturnsClient(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');

        $registry = new AuthClientRegistry($client);
        self::assertSame($client, $registry->get('github'));
    }

    public function testGetReturnsNullForUnknownClient(): void
    {
        $registry = new AuthClientRegistry();
        self::assertNull($registry->get('nonexistent'));
    }
}
