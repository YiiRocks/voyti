<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Shared;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Shared\SocialConnectViewData;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2;

#[AllowMockObjectsWithoutExpectations]
final class SocialConnectViewDataTest extends TestCase
{
    public function testCreateBuildsProviderLinksExcludingGivenNames(): void
    {
        $github = $this->createMock(OAuth2::class);
        $github->method('getTitle')->willReturn('GitHub');

        $google = $this->createMock(OAuth2::class);
        $google->method('getTitle')->willReturn('Google');

        $clientCollection = new Collection(['github' => $github, 'google' => $google]);
        $url = new FakeUrlGenerator();

        $data = SocialConnectViewData::create(
            $clientCollection,
            $url,
            excludedProviders: ['google'],
            routeName: 'voyti/session-auth',
        );

        self::assertCount(1, $data->providers);
        self::assertSame('GitHub', $data->providers[0]->title);
        self::assertSame('//voyti/session-auth?authclient=github', $data->providers[0]->url);
    }

    public function testCreateDefaultsToNoExclusionsAndDefaultRoute(): void
    {
        $client = $this->createMock(OAuth2::class);
        $client->method('getTitle')->willReturn('GitHub');

        $clientCollection = new Collection(['github' => $client]);

        $data = SocialConnectViewData::create($clientCollection, new FakeUrlGenerator());

        self::assertCount(1, $data->providers);
        self::assertSame('//voyti/session-auth?authclient=github', $data->providers[0]->url);
    }

    public function testCreateReturnsEmptyProvidersWhenClientCollectionIsNull(): void
    {
        $data = SocialConnectViewData::create(null, new FakeUrlGenerator());

        self::assertSame([], $data->providers);
    }
}
