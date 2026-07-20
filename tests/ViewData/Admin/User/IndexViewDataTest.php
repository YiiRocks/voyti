<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\User;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\User\IndexViewData;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Translator\Translator;

final class IndexViewDataTest extends TestCase
{
    public function testCreateWithoutSwitchedIdentity(): void
    {
        $user = $this->createUser('jane');
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = new Translator('en', null, 'voyti');

        $data = IndexViewData::create(
            [$user],
            $paginator,
            ['username' => 'jane'],
            new ModuleConfig(),
            new FakeUrlGenerator(),
            $translator,
            false,
            null,
            999999,
        );

        self::assertCount(1, $data->users);
        self::assertSame('jane', $data->users[0]->username);
        self::assertNull($data->switchedBannerMessage);
        self::assertSame(['username' => 'jane', 'email' => '', 'status' => ''], $data->filters);
        self::assertSame('//voyti/admin-users-create', $data->createUserUrl);
    }

    public function testCreateWithSwitchedIdentity(): void
    {
        $originalUser = $this->createUser('admin');
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = $this->createTranslator();

        $data = IndexViewData::create(
            [],
            $paginator,
            [],
            new ModuleConfig(),
            new FakeUrlGenerator(),
            $translator,
            true,
            $originalUser,
            999999,
        );

        self::assertNotNull($data->switchedBannerMessage);
        self::assertStringContainsString('admin', $data->switchedBannerMessage);
    }

    private function createUser(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username . '@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());

        return $user;
    }
}
