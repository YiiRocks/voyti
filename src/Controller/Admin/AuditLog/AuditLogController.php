<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\AuditLog;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Admin\AuditLog\IndexViewData;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Admin listing of {@see UserAuditLog} entries, with actor/target/action filters and pagination.
 */
final readonly class AuditLogController
{
    use InputDataTrait;
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private ResponseFactoryInterface $responseFactory,
        private ModuleConfig $config,
        private FlashInterface $flash,
        private CurrentUser $currentUser,
    ) {}

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $this->queryParams($request);
        $filters = [
            'actor_user_id' => $this->stringValue($queryParams, 'actorUserId'),
            'target_user_id' => $this->stringValue($queryParams, 'targetUserId'),
            'action' => $this->stringValue($queryParams, 'action'),
        ];

        $reader = new QueryDataReader(UserAuditLog::search($filters));
        $paginator = (new OffsetPaginator($reader))->withPageSize(50);
        $requestedPage = max(1, (int) ($queryParams['page'] ?? 1));
        $paginator = $paginator->withCurrentPage(min($requestedPage, max(1, $paginator->getTotalPages())));

        /** @var list<UserAuditLog> $logs */
        $logs = iterator_to_array($paginator->read(), false);

        $viewer = $this->currentUser->getIdentity();
        $viewerTimezone = $viewer instanceof User ? $viewer->getProfile()?->getTimezone() : null;

        return $this->renderView('admin/audit-log/index', [
            'data' => IndexViewData::create(
                array_map(fn(UserAuditLog $log): array => $this->presentLog($log, $viewerTimezone), $logs),
                $paginator,
                $filters,
                $this->url,
                $this->translator(),
            ),
        ]);
    }

    /**
     * @return array{createdAt: string, actorUserId: string, action: string, targetLabel: string, context: string}
     */
    private function presentLog(UserAuditLog $log, ?string $viewerTimezone): array
    {
        return [
            'createdAt' => TimezoneHelper::formatLocalized(
                $log->getCreatedAt(),
                $this->translator->getLocale(),
                $viewerTimezone,
            ),
            'actorUserId' => (string) ($log->getActorUserId() ?? ''),
            'action' => $log->getAction(),
            'targetLabel' => $this->targetLabel($log),
            'context' => $log->getContext() ?? '',
        ];
    }

    private function targetLabel(UserAuditLog $log): string
    {
        $name = $log->getTargetName() ?? '';
        $userId = $log->getTargetUserId();

        return $userId !== null ? $name . ' (#' . $userId . ')' : $name;
    }

}
