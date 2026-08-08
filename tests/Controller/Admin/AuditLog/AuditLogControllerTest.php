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

    public function testIndexAppliesDefaultTimezoneWhenViewerHasNoProfile(): void
    {
        $log = new UserAuditLog();
        $log->setAction('system.cleanup');
        $log->setCreatedAt(1700000000);
        $log->save();

        // A logged-in viewer with no profile must not break timezone resolution (nullsafe access).
        $this->currentUser->login($this->createUser(username: 'noprofile', email: 'noprofile@example.com'));

        $html = (string) $this->createController()->index()->getBody();

        self::assertStringContainsString(
            TimezoneHelper::formatLocalized(1700000000, $this->createTranslator()->getLocale(), null),
            $html,
        );
    }

    public function testIndexClampsPageBeyondLastToLastPage(): void
    {
        $this->createLog(1, 2, 'user.create');

        $html = (string) $this->createController()->index(page: 3)->getBody();

        // Requesting a page past the last clamps back to the (only) page with the log on it.
        self::assertStringContainsString('user.create', $html);
    }

    public function testIndexFiltersByActor(): void
    {
        $this->createLog(100, 2, 'a.first');
        $this->createLog(200, 2, 'a.second');

        $html = (string) $this->createController()->index(filterActorUserId: '100')->getBody();

        self::assertStringContainsString('#100', $html);
        self::assertStringNotContainsString('#200', $html);
    }

    public function testIndexPaginatesAtFiftyPerPageWithFiltersInPageLinks(): void
    {
        for ($i = 0; $i < 51; $i++) {
            $this->createLog(1, 2, 'user.login');
        }

        $html = (string) $this->createController()->index(filterAction: 'user.login')->getBody();

        // A full first page holds exactly 50 rows and a second page link exists.
        self::assertSame(50, substr_count($html, 'row py-2 border-bottom align-items-center'));
        // Both the next-page (pageUrlPattern) and first-page (firstPageUrl) links carry the active
        // filter as flat query keys through the URL.
        self::assertStringContainsString('action=user.login&amp;page=2', $html);
        self::assertStringContainsString('action=user.login&amp;page=1', $html);
    }

    public function testIndexPresentsLogFieldsFormattedForDisplay(): void
    {
        $actor = $this->createUser(username: 'jane_admin');

        $log = new UserAuditLog();
        $log->setActorUserId($actor->getIdOrZero());
        $log->setAction('rbac.role.update');
        $log->setTargetName('editor');
        $log->setTargetUserId(7);
        $log->setContext('{"previousName":"old-editor"}');
        $log->setCreatedAt(1700000000);
        $log->save();

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

    public function testIndexPresentsTargetLabelWithIdOnlyWhenUserNoLongerExists(): void
    {
        $this->createLog(1, 999999, 'user.switch_identity');

        $html = (string) $this->createController()->index()->getBody();

        self::assertStringContainsString('#999999', $html);
    }

    public function testIndexPresentsTargetNameWhenNoTargetUserId(): void
    {
        $log = new UserAuditLog();
        $log->setActorUserId(1);
        $log->setAction('rbac.role.delete');
        $log->setTargetName('some-role');
        $log->setCreatedAt(time());
        $log->save();

        $html = (string) $this->createController()->index()->getBody();

        // With no target user id, the raw target name is shown - and not the "name (#id)" form.
        self::assertStringContainsString('some-role', $html);
        self::assertStringNotContainsString('some-role (#', $html);
    }

    public function testIndexResolvesMultipleActorUsernames(): void
    {
        $alice = $this->createUser(username: 'alice', email: 'alice@example.com');
        $bob = $this->createUser(username: 'bob', email: 'bob@example.com');
        $this->createLog($alice->getIdOrZero(), 0, 'a.one');
        $this->createLog($bob->getIdOrZero(), 0, 'a.two');

        $html = (string) $this->createController()->index()->getBody();

        // Both distinct actors are resolved to their usernames, not just the first.
        self::assertStringContainsString('alice (#' . $alice->getIdOrZero() . ')', $html);
        self::assertStringContainsString('bob (#' . $bob->getIdOrZero() . ')', $html);
    }

    public function testIndexResolvesTargetUsernameWhenTargetNameWasNotCaptured(): void
    {
        $target = $this->createUser(username: 'switcheduser');

        // A distinct (non-existent) actor so the only source of "switcheduser" is the target label.
        $log = new UserAuditLog();
        $log->setActorUserId(888888);
        $log->setTargetUserId($target->getIdOrZero());
        $log->setAction('user.switch_identity');
        $log->setCreatedAt(time());
        $log->save();

        $html = (string) $this->createController()->index()->getBody();

        self::assertStringContainsString('switcheduser (#' . $target->getIdOrZero() . ')', $html);
    }

    public function testIndexTreatsNonPositivePageAsFirstPage(): void
    {
        $this->createLog(1, 2, 'user.create');

        $html = (string) $this->createController()->index(page: 0)->getBody();

        // A non-positive page is floored to 1 rather than passed through to the paginator.
        self::assertStringContainsString('user.create', $html);
    }

    public function testIndexWithNoResultsHasNoPaginationItems(): void
    {
        $html = (string) $this->createController()->index()->getBody();

        // The page renders, but with no logs there are no pagination items.
        self::assertStringContainsString('Audit Log', $html);
        self::assertStringNotContainsString('page-item', $html);
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
