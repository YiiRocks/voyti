<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\AuditLog;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\ViewData\Admin\AuditLog\IndexViewData;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
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
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private ResponseFactoryInterface $responseFactory,
        private VoytiConfig $config,
        private FlashInterface $flash,
        private CurrentUser $currentUser,
    ) {}

    public function index(
        #[Query('actorUserId')]
        string $filterActorUserId = '',
        #[Query('targetUserId')]
        string $filterTargetUserId = '',
        #[Query('action')]
        string $filterAction = '',
        #[Query('page')]
        int $page = 1,
    ): ResponseInterface {
        $filters = [
            'actor_user_id' => $filterActorUserId,
            'target_user_id' => $filterTargetUserId,
            'action' => $filterAction,
        ];

        $reader = new QueryDataReader(UserAuditLog::search($filters));
        $paginator = (new OffsetPaginator($reader))->withPageSize(50);
        $requestedPage = max(1, $page);
        $paginator = $paginator->withCurrentPage(min($requestedPage, max(1, $paginator->getTotalPages())));

        /** @var list<UserAuditLog> $logs */
        $logs = iterator_to_array($paginator->read(), false);

        $viewer = $this->currentUser->getIdentity();
        $viewerTimezone = $viewer instanceof User ? $viewer->getProfile()?->getTimezone() : null;

        $usernames = $this->resolveUsernames($logs);

        return $this->renderView('admin/audit-log/index', [
            'data' => IndexViewData::create(
                array_map(fn(UserAuditLog $log): array => $this->presentLog($log, $viewerTimezone, $usernames), $logs),
                $paginator,
                $filters,
                $this->url,
                $this->translator(),
            ),
        ]);
    }

    /**
     * @param array<int, string> $usernames
     */
    private function actorLabel(UserAuditLog $log, array $usernames): string
    {
        $userId = $log->getActorUserId();
        if ($userId === null) {
            return '';
        }

        return array_key_exists($userId, $usernames) ? $usernames[$userId] . ' (#' . $userId . ')' : '#' . $userId;
    }

    /**
     * @param array<int, string> $usernames
     *
     * @return array{createdAt: string, actorLabel: string, action: string, targetLabel: string, context: string}
     */
    private function presentLog(UserAuditLog $log, ?string $viewerTimezone, array $usernames): array
    {
        return [
            'createdAt' => TimezoneHelper::formatLocalized(
                $log->getCreatedAt(),
                $this->translator->getLocale(),
                $viewerTimezone,
            ),
            'actorLabel' => $this->actorLabel($log, $usernames),
            'action' => $log->getAction(),
            'targetLabel' => $this->targetLabel($log, $usernames),
            'context' => $log->getContext() ?? '',
        ];
    }

    /**
     * @param list<UserAuditLog> $logs
     *
     * @return array<int, string> user id => username, covering both actors and user targets
     */
    private function resolveUsernames(array $logs): array
    {
        $ids = [
            ...array_map(static fn(UserAuditLog $log): ?int => $log->getActorUserId(), $logs),
            ...array_map(static fn(UserAuditLog $log): ?int => $log->getTargetUserId(), $logs),
        ];
        $ids = array_values(array_unique(array_filter($ids)));

        $usernames = [];
        foreach (User::findByIds($ids) as $user) {
            $usernames[$user->getIdOrZero()] = $user->getUsername();
        }
        return $usernames;
    }

    /**
     * @param array<int, string> $usernames
     */
    private function targetLabel(UserAuditLog $log, array $usernames): string
    {
        $userId = $log->getTargetUserId();
        if ($userId === null) {
            return $log->getTargetName() ?? '';
        }

        $name = $log->getTargetName();
        $name = $name !== null && $name !== '' ? $name : ($usernames[$userId] ?? null);

        return $name !== null ? $name . ' (#' . $userId . ')' : '#' . $userId;
    }
}
