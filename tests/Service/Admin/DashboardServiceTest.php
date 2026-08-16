<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Admin;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\Admin\DashboardService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\SimpleAssignmentsStorage;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class DashboardServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;
    use UserSessionFactoryTrait;

    private SimpleItemsStorage $itemsStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->itemsStorage = new SimpleItemsStorage();
    }

    public function testGetStatsActiveSessionsTrendCountsSessionsWithinEachWindowBoundaryInclusive(): void
    {
        $lifespan = (VoytiConfigFactory::create())->rememberLoginLifespan;
        $user = $this->createUser('sessions-user', 'sessions-user@example.com', confirmedAt: time());
        $userId = (int) $user->getId();

        do {
            (new UserSessions())->deleteAll(['user_id' => $userId]);
            $now = time();
            foreach ($this->trendBoundaryOffsets($lifespan) as $label => $offset) {
                $this->createUserSession($userId, $label, createdAt: $now + $offset);
            }
            $stats = $this->createService()->getStats();
        } while (time() !== $now);

        self::assertSame(2, $stats['activeSessions']['oneDay']);
        self::assertSame(4, $stats['activeSessions']['sevenDays']);
        self::assertSame(6, $stats['activeSessions']['lifespan']);
    }

    public function testGetStatsCountsUsersByStatusIndependently(): void
    {
        $this->createUser('confirmed', 'confirmed@example.com', confirmedAt: time());
        $this->createUser('unconfirmed', 'unconfirmed@example.com');
        $this->createUser('blocked', 'blocked@example.com', confirmedAt: time(), blockedAt: time());

        $stats = $this->createService()->getStats();

        self::assertSame(3, $stats['userTotal']);
        self::assertSame(1, $stats['userBlocked']);
        self::assertSame(1, $stats['userUnconfirmed']);
    }

    public function testGetStatsNewRegistrationsTrendCountsUsersWithinEachWindowBoundaryInclusive(): void
    {
        $lifespan = (VoytiConfigFactory::create())->rememberLoginLifespan;
        $offsets = $this->trendBoundaryOffsets($lifespan);
        $emails = array_map(static fn(string $label): string => $label . '@example.com', array_keys($offsets));

        do {
            (new User())->deleteAll(['email' => $emails]);
            $now = time();
            foreach ($offsets as $label => $offset) {
                $this->createUser($label, $label . '@example.com', createdAt: $now + $offset, confirmedAt: time());
            }
            $stats = $this->createService()->getStats();
        } while (time() !== $now);

        self::assertSame(2, $stats['newRegistrations']['oneDay']);
        self::assertSame(4, $stats['newRegistrations']['sevenDays']);
        self::assertSame(6, $stats['newRegistrations']['lifespan']);
    }

    public function testGetStatsRecentAuditLogsTargetLabelIncludesUserIdOnlyWhenPresent(): void
    {
        $withTarget = new UserAuditLog();
        $withTarget->setAction('user.block');
        $withTarget->setTargetName('someone');
        $withTarget->setTargetUserId(42);
        $withTarget->setCreatedAt(2);
        $withTarget->save();

        $withoutTarget = new UserAuditLog();
        $withoutTarget->setAction('system.cleanup');
        $withoutTarget->setCreatedAt(1);
        $withoutTarget->save();

        $stats = $this->createService()->getStats();

        self::assertSame('someone (#42)', $stats['recentAuditLogs'][0]['targetLabel']);
        self::assertSame('', $stats['recentAuditLogs'][1]['targetLabel']);
    }

    public function testGetStatsRecommendedPackagesFeatureFlagAndStructure(): void
    {
        // Feature flag disabled — empty array
        $config = VoytiConfigFactory::create(enableRecommendations: false);
        $stats = $this->createService($config)->getStats();
        self::assertSame([], $stats['recommendedPackages']);

        // Feature flag enabled — validate all packages, structure, and URLs
        $stats = $this->createService()->getStats();
        $packages = $stats['recommendedPackages'];
        $packageNames = array_column($packages, 'packageName');

        // Verify all sibling packages are included
        $expectedPackages = [
            'yiirocks/voyti-api',
            'yiirocks/voyti-gdpr',
            'yiirocks/voyti-social-auth',
            'yiirocks/voyti-2fa-email',
            'yiirocks/voyti-2fa-totp',
            'yiirocks/voyti-2fa-webauthn',
        ];
        foreach ($expectedPackages as $expected) {
            self::assertContains($expected, $packageNames);
        }

        // Validate structure and URLs for each package
        $validSlugs = ['api', 'gdpr', 'social', 'two-factor'];
        foreach ($packages as $package) {
            self::assertArrayHasKey('packageName', $package);
            self::assertArrayHasKey('labelKey', $package);
            self::assertArrayHasKey('descriptionKey', $package);
            self::assertArrayHasKey('composerUrl', $package);
            self::assertArrayHasKey('docsUrl', $package);
            self::assertIsString($package['packageName']);
            self::assertIsString($package['labelKey']);
            self::assertIsString($package['descriptionKey']);
            self::assertIsString($package['composerUrl']);
            self::assertIsString($package['docsUrl']);

            self::assertStringStartsWith(
                'https://packagist.org/packages/',
                $package['composerUrl'],
            );
            self::assertStringEndsWith($package['packageName'], $package['composerUrl']);

            self::assertStringStartsWith('https://www.yii.rocks/voyti/', $package['docsUrl']);
            self::assertStringEndsWith('/', $package['docsUrl']);
            $slug = str_replace('https://www.yii.rocks/voyti/', '', rtrim($package['docsUrl'], '/'));
            self::assertContains($slug, $validSlugs);
        }
    }

    public function testGetStatsRecommendedPackagesKeysAreValid(): void
    {
        $stats = $this->createService()->getStats();
        $packages = $stats['recommendedPackages'];

        foreach ($packages as $package) {
            self::assertStringStartsWith('voyti.view.dashboard.package_', $package['labelKey']);
            self::assertStringStartsWith('voyti.view.dashboard.package_', $package['descriptionKey']);
            self::assertStringEndsWith('_label', $package['labelKey']);
            self::assertStringEndsWith('_description', $package['descriptionKey']);
        }
    }

    public function testGetStatsRememberLifespanDaysRoundsDownBelowHalfADay(): void
    {
        $config = VoytiConfigFactory::create(rememberLoginLifespan: 100000);

        $stats = $this->createService($config)->getStats();

        self::assertSame(1, $stats['rememberLifespanDays']);
    }

    public function testGetStatsRememberLifespanDaysRoundsUpAboveHalfADay(): void
    {
        $config = VoytiConfigFactory::create(rememberLoginLifespan: 130000);

        $stats = $this->createService($config)->getStats();

        self::assertSame(2, $stats['rememberLifespanDays']);
    }

    public function testGetStatsUserUnconfirmedIsNullWhenEmailConfirmationDisabled(): void
    {
        $this->createUser('unconfirmed', 'unconfirmed@example.com');

        $stats = $this->createService(VoytiConfigFactory::create(enableEmailConfirmation: false))->getStats();

        self::assertNull($stats['userUnconfirmed']);
    }

    private function createLog(string $action, int $createdAt): void
    {
        $log = new UserAuditLog();
        $log->setAction($action);
        $log->setCreatedAt($createdAt);
        $log->save();
    }

    private function createService(?VoytiConfig $config = null, string $locale = 'en'): DashboardService
    {
        $config ??= VoytiConfigFactory::create();
        $assignmentsStorage = new SimpleAssignmentsStorage();
        $manager = new Manager($this->itemsStorage, $assignmentsStorage);
        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );
        $authHelper = new AuthHelper($manager, $this->itemsStorage, $assignmentsStorage, $config, $currentUser);
        $translator = $this->createTranslator($locale);

        return new DashboardService($authHelper, $config, $this->itemsStorage, $translator);
    }

    private function trendBoundaryOffsets(int $lifespan): array
    {
        return [
            'within-day' => 0,
            'at-day-cutoff' => -86400,
            'just-outside-day' => -86400 - 1,
            'at-week-cutoff' => -(86400 * 7),
            'just-outside-week' => -(86400 * 7) - 1,
            'at-lifespan-cutoff' => -$lifespan,
            'outside-lifespan' => -$lifespan - 1,
        ];
    }
}
