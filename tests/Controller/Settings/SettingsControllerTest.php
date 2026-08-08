<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Settings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use YiiRocks\Voyti\Controller\Settings\SettingsController;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class SettingsControllerTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use TestContainerTrait;
    use UserFactoryTrait;

    private FlashInterface&MockObject $flash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flash = $this->createMock(FlashInterface::class);
    }

    public function testIndexShowsView(): void
    {
        $user = $this->createUser();

        $html = (string) $this->createController($this->createCurrentUser($user))->index()->getBody();

        self::assertStringContainsString('Member since', $html);
    }

    private function createController(?CurrentUser $currentUser = null): SettingsController
    {
        return $this->getTestContainer([
            CurrentUser::class => $currentUser ?? $this->createCurrentUser(),
            FlashInterface::class => $this->flash,
        ])->get(SettingsController::class);
    }
}
