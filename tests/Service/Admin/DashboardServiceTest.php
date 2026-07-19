<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Admin;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Admin\DashboardService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\SimpleAssignmentsStorage;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class DashboardServiceTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

    private SimpleItemsStorage $itemsStorage;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->itemsStorage = new SimpleItemsStorage();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testGetStatsActiveSessionsTrendCountsSessionsWithinEachWindowBoundaryInclusive(): void
    {
        $lifespan = (new ModuleConfig())->rememberLoginLifespan;
        $user = $this->createUser('sessions-user', 'sessions-user@example.com', confirmedAt: time());
        $userId = (int) $user->getId();

        do {
            (new UserSessions())->deleteAll(['user_id' => $userId]);
            $now = time();
            foreach ($this->trendBoundaryOffsets($lifespan) as $label => $offset) {
                $this->createUserSession($userId, $label, $now + $offset);
            }
            $stats = $this->createService()->getStats();
        } while (time() !== $now);

        self::assertSame(2, $stats['activeSessions']['oneDay']);
        self::assertSame(4, $stats['activeSessions']['sevenDays']);
        self::assertSame(6, $stats['activeSessions']['lifespan']);
    }

    public function testGetStatsActiveSessionsTrendExcludesRevokedSessions(): void
    {
        $user = $this->createUser('revoked-sessions-user', 'revoked-sessions-user@example.com', confirmedAt: time());
        $userId = (int) $user->getId();

        $session = $this->createUserSession($userId, 'revoked-recent', time());
        $session->setRevokedAt(time());
        $session->save();

        $stats = $this->createService()->getStats();

        self::assertSame(0, $stats['activeSessions']['oneDay']);
    }

    public function testGetStatsActiveSessionsTrendFiltersByUpdatedAtNotCreatedAt(): void
    {
        $lifespan = (new ModuleConfig())->rememberLoginLifespan;
        $user = $this->createUser('updated-at-user', 'updated-at-user@example.com', confirmedAt: time());
        $userId = (int) $user->getId();

        $staleCreatedRecentlyUpdated = new UserSessions();
        $staleCreatedRecentlyUpdated->setUserId($userId);
        $staleCreatedRecentlyUpdated->setSessionId('stale-created-recently-updated');
        $staleCreatedRecentlyUpdated->setIp('127.0.0.1');
        $staleCreatedRecentlyUpdated->setCreatedAt(time() - $lifespan - 1);
        $staleCreatedRecentlyUpdated->setUpdatedAt(time());
        $staleCreatedRecentlyUpdated->save();

        $recentlyCreatedStaleUpdated = new UserSessions();
        $recentlyCreatedStaleUpdated->setUserId($userId);
        $recentlyCreatedStaleUpdated->setSessionId('recently-created-stale-updated');
        $recentlyCreatedStaleUpdated->setIp('127.0.0.1');
        $recentlyCreatedStaleUpdated->setCreatedAt(time());
        $recentlyCreatedStaleUpdated->setUpdatedAt(time() - $lifespan - 1);
        $recentlyCreatedStaleUpdated->save();

        $stats = $this->createService()->getStats();

        self::assertSame(1, $stats['activeSessions']['oneDay']);
    }

    public function testGetStatsCountsRbacItemsAndDistinctRuleNames(): void
    {
        $this->itemsStorage->add(new Role('admin'));
        $this->itemsStorage->add((new Role('editor'))->withRuleName('IsAuthorRule'));
        $this->itemsStorage->add((new Permission('post.create'))->withRuleName('IsAuthorRule'));
        $this->itemsStorage->add(new Permission('post.delete'));
        $this->itemsStorage->add(new Permission('post.update'));

        $stats = $this->createService()->getStats();

        self::assertSame(2, $stats['roleCount']);
        self::assertSame(3, $stats['permissionCount']);
        self::assertSame(1, $stats['ruleCount']);
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
        $lifespan = (new ModuleConfig())->rememberLoginLifespan;
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

    public function testGetStatsRecentAuditLogsEmptyWhenNoneExist(): void
    {
        $stats = $this->createService()->getStats();

        self::assertSame([], $stats['recentAuditLogs']);
    }

    public function testGetStatsRecentAuditLogsFormatCreatedAtUsingTranslatorLocale(): void
    {
        $timestamp = 1700000000;
        $this->createLog('user.create', $timestamp);

        $stats = $this->createService(locale: 'fr')->getStats();

        self::assertSame(
            TimezoneHelper::formatLocalized($timestamp, 'fr'),
            $stats['recentAuditLogs'][0]['createdAt'],
        );
    }

    public function testGetStatsRecentAuditLogsOrderedNewestFirstLimitedToFive(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createLog('action.' . $i, $i);
        }

        $stats = $this->createService()->getStats();

        self::assertCount(5, $stats['recentAuditLogs']);
        self::assertSame('action.7', $stats['recentAuditLogs'][0]['action']);
        self::assertSame('action.3', $stats['recentAuditLogs'][4]['action']);
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

    public function testGetStatsRememberLifespanDaysRoundsDownBelowHalfADay(): void
    {
        $config = new ModuleConfig(rememberLoginLifespan: 100000);

        $stats = $this->createService($config)->getStats();

        self::assertSame(1, $stats['rememberLifespanDays']);
    }

    public function testGetStatsRememberLifespanDaysRoundsUpAboveHalfADay(): void
    {
        $config = new ModuleConfig(rememberLoginLifespan: 130000);

        $stats = $this->createService($config)->getStats();

        self::assertSame(2, $stats['rememberLifespanDays']);
    }

    public function testGetStatsUserUnconfirmedIsNullWhenEmailConfirmationDisabled(): void
    {
        $this->createUser('unconfirmed', 'unconfirmed@example.com');

        $stats = $this->createService(new ModuleConfig(enableEmailConfirmation: false))->getStats();

        self::assertNull($stats['userUnconfirmed']);
    }

    private function createLog(string $action, int $createdAt): void
    {
        $log = new UserAuditLog();
        $log->setAction($action);
        $log->setCreatedAt($createdAt);
        $log->save();
    }

    private function createService(?ModuleConfig $config = null, string $locale = 'en'): DashboardService
    {
        $config ??= new ModuleConfig();
        $assignmentsStorage = new SimpleAssignmentsStorage();
        $manager = new Manager($this->itemsStorage, $assignmentsStorage);
        $currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );
        $authHelper = new AuthHelper($manager, $this->itemsStorage, $assignmentsStorage, $config, $currentUser);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('getLocale')->willReturn($locale);

        return new DashboardService($authHelper, $config, $this->itemsStorage, $translator);
    }

    private function createUserSession(int $userId, string $sessionId, int $createdAt): UserSessions
    {
        $session = new UserSessions();
        $session->setUserId($userId);
        $session->setSessionId($sessionId);
        $session->setIp('127.0.0.1');
        $session->setCreatedAt($createdAt);
        $session->setUpdatedAt($createdAt);
        $session->save();

        return $session;
    }

    /**
     * @return array<string, int> offset in seconds relative to "now", keyed by fixture label
     */
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
