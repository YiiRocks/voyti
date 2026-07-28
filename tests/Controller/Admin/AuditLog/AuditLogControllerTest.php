<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\AuditLog;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Admin\AuditLog\AuditLogController;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\ViewCaptureTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class AuditLogControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use TestContainerTrait;
    use UserFactoryTrait;
    use ViewCaptureTrait;

    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->viewRenderer->method('withAddedInjections')->willReturnSelf();
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->currentUser = $this->createMock(CurrentUser::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testIndexFiltersByAction(): void
    {
        $this->createLog(1, 2, 'user.create');
        $this->createLog(1, 2, 'user.delete');

        [$state, $response] = $this->captureRenderedView($this->viewRenderer);

        $controller = $this->createController();
        $controller->index(filterAction: 'create');

        $this->assertCount(1, $state->params['data']->logs);
    }

    public function testIndexPresentsActorLabelWithIdOnlyWhenUserNoLongerExists(): void
    {
        $this->createLog(999999, 2, 'user.delete');

        [$state, $response] = $this->captureRenderedView($this->viewRenderer);

        $controller = $this->createController();
        $controller->index();

        self::assertSame('#999999', $state->params['data']->logs[0]['actorLabel']);
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

        $viewerProfile = new UserProfile();
        $viewerProfile->setTimezone('Asia/Tokyo');
        $viewer = $this->createMock(User::class);
        $viewer->method('getProfile')->willReturn($viewerProfile);
        $this->currentUser->method('getIdentity')->willReturn($viewer);

        [$state, $response] = $this->captureRenderedView($this->viewRenderer);

        $controller = $this->createController();
        $controller->index();

        self::assertSame(
            [
                'createdAt' => TimezoneHelper::formatLocalized(
                    1700000000,
                    $this->createTranslator()->getLocale(),
                    'Asia/Tokyo',
                ),
                'actorLabel' => 'jane_admin (#' . $actor->getIdOrZero() . ')',
                'action' => 'rbac.role.update',
                'targetLabel' => 'editor (#7)',
                'context' => '{"previousName":"old-editor"}',
            ],
            $state->params['data']->logs[0],
        );
    }

    public function testIndexPresentsLogWithoutTargetOrContext(): void
    {
        $log = new UserAuditLog();
        $log->setAction('system.cleanup');
        $log->setCreatedAt(1700000000);
        $log->save();

        [$state, $response] = $this->captureRenderedView($this->viewRenderer);

        $controller = $this->createController();
        $controller->index();

        self::assertSame('', $state->params['data']->logs[0]['actorLabel']);
        self::assertSame('', $state->params['data']->logs[0]['targetLabel']);
        self::assertSame('', $state->params['data']->logs[0]['context']);
    }

    public function testIndexPresentsTargetLabelWithIdOnlyWhenUserNoLongerExists(): void
    {
        $this->createLog(1, 999999, 'user.switch_identity');

        [$state, $response] = $this->captureRenderedView($this->viewRenderer);

        $controller = $this->createController();
        $controller->index();

        self::assertSame('#999999', $state->params['data']->logs[0]['targetLabel']);
    }

    public function testIndexResolvesTargetUsernameWhenTargetNameWasNotCaptured(): void
    {
        $target = $this->createUser(username: 'switcheduser');

        $log = new UserAuditLog();
        $log->setActorUserId(1);
        $log->setTargetUserId($target->getIdOrZero());
        $log->setAction('user.switch_identity');
        $log->setCreatedAt(time());
        $log->save();

        [$state, $response] = $this->captureRenderedView($this->viewRenderer);

        $controller = $this->createController();
        $controller->index();

        self::assertSame('switcheduser (#' . $target->getIdOrZero() . ')', $state->params['data']->logs[0]['targetLabel']);
    }

    public function testIndexShowsLogs(): void
    {
        $this->createLog(1, 2, 'user.create');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/audit-log/index', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testIndexWithNoResultsPaginatorHasNoPages(): void
    {
        [$state, $response] = $this->captureRenderedView($this->viewRenderer);

        $controller = $this->createController();
        $controller->index();

        $this->assertInstanceOf(OffsetPaginator::class, $state->params['data']->paginator);
        $this->assertSame(0, $state->params['data']->paginator->getTotalPages());
    }

    private function createController(): AuditLogController
    {
        return $this->getTestContainer([
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
            WebViewRenderer::class => $this->viewRenderer,
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
