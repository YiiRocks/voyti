<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Admin;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\AuditLog;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Admin\DashboardService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\SimpleAssignmentsStorage;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class DashboardServiceTest extends TestCase
{
    use DatabaseSetupTrait;

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
        $this->createUser('confirmed', 'confirmed@example.com', confirmed: true, blocked: false);
        $this->createUser('unconfirmed', 'unconfirmed@example.com', confirmed: false, blocked: false);
        $this->createUser('blocked', 'blocked@example.com', confirmed: true, blocked: true);

        $stats = $this->createService()->getStats();

        self::assertSame(3, $stats['userTotal']);
        self::assertSame(1, $stats['userBlocked']);
        self::assertSame(1, $stats['userUnconfirmed']);
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
        $withTarget = new AuditLog();
        $withTarget->setAction('user.block');
        $withTarget->setTargetName('someone');
        $withTarget->setTargetUserId(42);
        $withTarget->setCreatedAt(2);
        $withTarget->save();

        $withoutTarget = new AuditLog();
        $withoutTarget->setAction('system.cleanup');
        $withoutTarget->setCreatedAt(1);
        $withoutTarget->save();

        $stats = $this->createService()->getStats();

        self::assertSame('someone (#42)', $stats['recentAuditLogs'][0]['targetLabel']);
        self::assertSame('', $stats['recentAuditLogs'][1]['targetLabel']);
    }

    public function testGetStatsUserUnconfirmedIsNullWhenEmailConfirmationDisabled(): void
    {
        $this->createUser('unconfirmed', 'unconfirmed@example.com', confirmed: false, blocked: false);

        $stats = $this->createService(new ModuleConfig(enableEmailConfirmation: false))->getStats();

        self::assertNull($stats['userUnconfirmed']);
    }

    private function createLog(string $action, int $createdAt): void
    {
        $log = new AuditLog();
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

    private function createUser(string $username, string $email, bool $confirmed, bool $blocked): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setConfirmedAt($confirmed ? time() : null);
        $user->setBlockedAt($blocked ? time() : null);
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }
}
