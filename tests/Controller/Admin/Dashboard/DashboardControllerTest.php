<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\Dashboard;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Controller\Admin\Dashboard\DashboardController;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class DashboardControllerTest extends DatabaseTestCase
{
    use TestContainerTrait;
    use UserFactoryTrait;

    private CurrentUser $currentUser;
    private FlashInterface&MockObject $flash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );
    }

    public function testIndexOmitsUnconfirmedTileWhenEmailConfirmationDisabled(): void
    {
        $admin = $this->createUser(username: 'admin', email: 'admin@example.com');
        $this->currentUser->overrideIdentity($admin);

        $html = (string) $this->createController([
            VoytiConfig::class => VoytiConfigFactory::create(enableEmailConfirmation: false),
        ])->index()->getBody();

        self::assertStringContainsString('<div class="fs-2 fw-bold">1</div>', $html);
        self::assertStringNotContainsString('Unconfirmed users', $html);
    }

    public function testIndexPassesViewerTimezoneToDashboardService(): void
    {
        $viewer = $this->createUser(username: 'viewer', email: 'viewer@example.com');
        $viewerProfile = new UserProfile();
        $viewerProfile->setUserId((int) $viewer->getId());
        $viewerProfile->setTimezone('Asia/Tokyo');
        $viewerProfile->save();
        $this->currentUser->overrideIdentity($viewer);

        $log = new UserAuditLog();
        $log->setAction('user.create');
        $log->setCreatedAt(1700000000);
        $log->save();

        $html = (string) $this->createController()->index()->getBody();

        // The viewer's profile timezone flows into DashboardService, which formats the audit-log
        // timestamps with it, and the formatted value is rendered into the dashboard.
        self::assertStringContainsString(
            TimezoneHelper::formatLocalized(1700000000, $this->createTranslator()->getLocale(), 'Asia/Tokyo'),
            $html,
        );
    }

    public function testIndexRendersDashboardViewWithStats(): void
    {
        $admin = $this->createUser(username: 'admin', email: 'admin@example.com');
        $this->currentUser->overrideIdentity($admin);
        $this->createUser(username: 'u1', email: 'u1@example.com');
        $blocked = $this->createUser(username: 'u2', email: 'u2@example.com');
        $blocked->setBlockedAt(time());
        $blocked->save();

        $html = (string) $this->createController()->index()->getBody();

        // Total users tile shows 3, the admin menu is present, and there is no recent activity yet.
        self::assertStringContainsString('<div class="fs-2 fw-bold">3</div>', $html);
        self::assertStringContainsString('Total users', $html);
        self::assertStringContainsString('Dashboard', $html);
        self::assertStringContainsString('No recent activity', $html);

        // Email confirmation is enabled, so the unconfirmed-users tile appears too.
        self::assertStringContainsString('Unconfirmed users', $html);

        // Both trend widgets render, including all three periods.
        self::assertStringContainsString('New registrations', $html);
        self::assertStringContainsString('Active sessions', $html);
        self::assertStringContainsString('Last 24 hours', $html);
    }

    private function createController(array $overrides = []): DashboardController
    {
        return $this->getTestContainer([
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
            ...$overrides,
        ])->get(DashboardController::class);
    }
}
