<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Service\Auth\SocialAuthClientReturnUrlConfigurator;
use YiiRocks\Voyti\tests\Support\FakeHttpClient;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use Yiisoft\Factory\Factory;
use Yiisoft\Yii\AuthClient\Client\GitHub;
use Yiisoft\Yii\AuthClient\Client\Google;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;

final class SocialAuthClientReturnUrlConfiguratorTest extends TestCase
{
    public function testConfigureDoesNotOverwriteAnAlreadyConfiguredReturnUrl(): void
    {
        $client = $this->makeClient(GitHub::class);
        $client->setOauth2ReturnUrl('https://host-configured.example.com/callback');
        $collection = new Collection(['github' => $client]);
        $url = new FakeUrlGenerator();
        $url->setUrl('voyti/session-auth', '/auth');

        (new SocialAuthClientReturnUrlConfigurator($url))->configure($collection);

        self::assertSame('https://host-configured.example.com/callback', $client->getOauth2ReturnUrl());
    }
    public function testConfigureFillsInReturnUrlUsingCollectionKey(): void
    {
        $client = $this->makeClient(GitHub::class);
        $collection = new Collection(['github' => $client]);
        $url = new FakeUrlGenerator();
        $url->setUrl('voyti/session-auth', '/auth');

        (new SocialAuthClientReturnUrlConfigurator($url))->configure($collection);

        self::assertSame('https://example.com/auth?authclient=github', $client->getOauth2ReturnUrl());
    }

    public function testConfigureReturnsTheSameCollectionInstance(): void
    {
        $collection = new Collection(['github' => $this->makeClient(GitHub::class)]);
        $url = new FakeUrlGenerator();
        $url->setUrl('voyti/session-auth', '/auth');

        $result = (new SocialAuthClientReturnUrlConfigurator($url))->configure($collection);

        self::assertSame($collection, $result);
    }

    public function testConfigureUsesEachClientsOwnCollectionKey(): void
    {
        $github = $this->makeClient(GitHub::class);
        $google = $this->makeClient(Google::class);
        $collection = new Collection(['github' => $github, 'google' => $google]);
        $url = new FakeUrlGenerator();
        $url->setUrl('voyti/session-auth', '/auth');

        (new SocialAuthClientReturnUrlConfigurator($url))->configure($collection);

        self::assertSame('https://example.com/auth?authclient=github', $github->getOauth2ReturnUrl());
        self::assertSame('https://example.com/auth?authclient=google', $google->getOauth2ReturnUrl());
    }

    /**
     * @param class-string<OAuth2> $class
     */
    private function makeClient(string $class): OAuth2
    {
        return new $class(
            new FakeHttpClient(new Response(200, [], '{}')),
            new Psr17Factory(),
            new DummyStateStorage(),
            new Factory(),
            new FakeSession(),
        );
    }
}
