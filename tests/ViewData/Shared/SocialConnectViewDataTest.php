<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Shared;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\AuthClientInterface;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Shared\SocialConnectViewData;

#[AllowMockObjectsWithoutExpectations]
final class SocialConnectViewDataTest extends TestCase
{
    public function testCreateBuildsProviderLinksExcludingGivenNames(): void
    {
        $github = $this->createMock(AuthClientInterface::class);
        $github->method('getName')->willReturn('github');
        $github->method('getTitle')->willReturn('GitHub');

        $google = $this->createMock(AuthClientInterface::class);
        $google->method('getName')->willReturn('google');
        $google->method('getTitle')->willReturn('Google');

        $registry = new AuthClientRegistry($github, $google);
        $url = new FakeUrlGenerator();

        $data = SocialConnectViewData::create($registry, $url, excludedProviders: ['google'], routeName: 'voyti/session-auth');

        self::assertCount(1, $data->providers);
        self::assertSame('GitHub', $data->providers[0]->title);
        self::assertSame('//voyti/session-auth?provider=github', $data->providers[0]->url);
    }

    public function testCreateDefaultsToNoExclusionsAndDefaultRoute(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $client->method('getTitle')->willReturn('GitHub');

        $data = SocialConnectViewData::create(new AuthClientRegistry($client), new FakeUrlGenerator());

        self::assertCount(1, $data->providers);
        self::assertSame('//voyti/session-auth?provider=github', $data->providers[0]->url);
    }
}
