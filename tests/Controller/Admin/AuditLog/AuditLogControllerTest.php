<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\AuditLog;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use YiiRocks\Voyti\Controller\Admin\AuditLog\AuditLogController;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class AuditLogControllerTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use TestContainerTrait;
    use UserFactoryTrait;

    private CurrentUser $currentUser;
    private FlashInterface&MockObject $flash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->currentUser = $this->createCurrentUser();
    }

    public function testIndexFilteringAndPagination(): void
    {
        // Filter by actor, echoing filter value back into form
        $this->createLog(100, 2, 'a.first');
        $this->createLog(200, 2, 'a.second');
        $html = (string) $this->createController()->index(filterActorUserId: '100')->getBody();
        self::assertStringContainsString('#100', $html);
        self::assertStringNotContainsString('#200', $html);
        self::assertStringContainsString('value="100"', $html);

        // Filter by target, echoing filter value back into form
        $html = (string) $this->createController()->index(filterTargetUserId: '2')->getBody();
        self::assertStringContainsString('#2', $html);
        self::assertStringContainsString('value="2"', $html);

        // Pagination with filter in links
        for ($i = 0; $i < 51; $i++) {
            $this->createLog(1, 2, 'user.login');
        }
        $html = (string) $this->createController()->index(filterAction: 'user.login')->getBody();
        self::assertSame(50, substr_count($html, 'row py-2 border-bottom align-items-center'));
        self::assertStringContainsString('action=user.login&amp;page=2', $html);
        self::assertStringContainsString('action=user.login&amp;page=1', $html);
    }

    public function testIndexFormatting(): void
    {
        // Timezone: default when viewer has no profile
        $log1 = new UserAuditLog();
        $log1->setAction('system.cleanup');
        $log1->setCreatedAt(1700000000);
        $log1->save();
        $this->currentUser->login($this->createUser(username: 'noprofile', email: 'noprofile@example.com'));
        $html = (string) $this->createController()->index()->getBody();
        self::assertStringContainsString(
            TimezoneHelper::formatLocalized(1700000000, $this->createTranslator()->getLocale(), null),
            $html,
        );

        // Timezone: uses viewer profile timezone
        $actor = $this->createUser(username: 'jane_admin');
        $log2 = new UserAuditLog();
        $log2->setActorUserId($actor->getIdOrZero());
        $log2->setAction('rbac.role.update');
        $log2->setTargetName('editor');
        $log2->setTargetUserId(7);
        $log2->setContext('{"previousName":"old-editor"}');
        $log2->setCreatedAt(1700000000);
        $log2->save();
        $viewer = $this->createUser(username: 'viewer', email: 'viewer@example.com');
        $viewerProfile = new UserProfile();
        $viewerProfile->setUserId((int) $viewer->getId());
        $viewerProfile->setTimezone('Asia/Tokyo');
        $viewerProfile->save();
        $this->currentUser->login($viewer);
        $html = (string) $this->createController()->index()->getBody();
        self::assertStringContainsString(
            TimezoneHelper::formatLocalized(1700000000, $this->createTranslator()->getLocale(), 'Asia/Tokyo'),
            $html,
        );
        self::assertStringContainsString('jane_admin (#' . $actor->getIdOrZero() . ')', $html);
        self::assertStringContainsString('rbac.role.update', $html);
        self::assertStringContainsString('editor (#7)', $html);
        self::assertStringContainsString('old-editor', $html);
    }

    public function testIndexPaginationEdgeCases(): void
    {
        // Clamp page beyond last
        $this->createLog(1, 2, 'user.create');
        $html = (string) $this->createController()->index(page: 3)->getBody();
        self::assertStringContainsString('user.create', $html);

        // Non-positive page treated as first
        $html = (string) $this->createController()->index(page: 0)->getBody();
        self::assertStringContainsString('user.create', $html);

        // No results: no pagination controls, empty filters show empty input values
        $html = (string) $this->createController()->index()->getBody();
        self::assertStringContainsString('Audit Log', $html);
        self::assertStringNotContainsString('page-item', $html);
        self::assertStringContainsString('name="actorUserId" value', $html);
        self::assertStringContainsString('name="targetUserId" value', $html);
    }

    public function testIndexTargetLabeling(): void
    {
        // Target user id when user exists
        $alice = $this->createUser(username: 'alice', email: 'alice@example.com');
        $this->createLog($alice->getIdOrZero(), 0, 'a.one');
        $html = (string) $this->createController()->index()->getBody();
        self::assertStringContainsString('alice (#' . $alice->getIdOrZero() . ')', $html);

        // Target with id only when user no longer exists
        $this->createLog(1, 999999, 'user.switch_identity');
        $html = (string) $this->createController()->index()->getBody();
        self::assertStringContainsString('#999999', $html);

        // Target name only when no target user id
        $log = new UserAuditLog();
        $log->setActorUserId(1);
        $log->setAction('rbac.role.delete');
        $log->setTargetName('some-role');
        $log->setCreatedAt(time());
        $log->save();
        $html = (string) $this->createController()->index()->getBody();
        self::assertStringContainsString('some-role', $html);
        self::assertStringNotContainsString('some-role (#', $html);

        // Falls back to username when targetName is null but targetUserId exists
        $bob = $this->createUser(username: 'bob', email: 'bob@example.com');
        $logWithNullName = new UserAuditLog();
        $logWithNullName->setActorUserId(1);
        $logWithNullName->setTargetUserId($bob->getIdOrZero());
        $logWithNullName->setAction('user.delete');
        $logWithNullName->setTargetName(null);
        $logWithNullName->setCreatedAt(time());
        $logWithNullName->save();
        $html = (string) $this->createController()->index()->getBody();
        self::assertStringContainsString('bob (#' . $bob->getIdOrZero() . ')', $html);

        // Falls back to username when targetName is empty string but targetUserId exists
        $charlie = $this->createUser(username: 'charlie', email: 'charlie@example.com');
        $logWithEmptyName = new UserAuditLog();
        $logWithEmptyName->setActorUserId(1);
        $logWithEmptyName->setTargetUserId($charlie->getIdOrZero());
        $logWithEmptyName->setAction('user.delete');
        $logWithEmptyName->setTargetName('');
        $logWithEmptyName->setCreatedAt(time());
        $logWithEmptyName->save();
        $html = (string) $this->createController()->index()->getBody();
        self::assertStringContainsString('charlie (#' . $charlie->getIdOrZero() . ')', $html);
    }

    private function createController(): AuditLogController
    {
        return $this->getTestContainer([
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
        ])->get(AuditLogController::class);
    }

    private function createLog(int $actorUserId, int $targetUserId, string $action): void
    {
        $log = new UserAuditLog();
        $log->setActorUserId($actorUserId);
        $log->setTargetUserId($targetUserId);
        $log->setAction($action);
        $log->setCreatedAt(time());
        $log->save();
    }
}
