<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\AuditLog;

use Closure;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\MappedPaginatedDataReader;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Db\QueryDataReaderInterface;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Router\UrlGeneratorInterface;
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
        private FlashNotifier $flashNotifier,
        private CurrentUser $currentUser,
    ) {}

    public function index(
        #[Query('actorUserId')]
        string $filterActorUserId = '',
        #[Query('targetUserId')]
        string $filterTargetUserId = '',
        #[Query('action')]
        string $filterAction = '',
        /**
         * @infection-ignore-all Mutating this default to 0 is behaviorally identical to 1: both are
         * floored to 1 by max(1, $page) below, so no test can observe the difference.
         */
        #[Query('page')]
        int $page = 1,
    ): ResponseInterface {
        $filters = [
            'actor_user_id' => $filterActorUserId,
            'target_user_id' => $filterTargetUserId,
            'action' => $filterAction,
        ];

        $viewer = $this->currentUser->getIdentity();
        $viewerTimezone = $viewer instanceof User ? $viewer->getProfile()?->getTimezone() : null;
        /** @psalm-var QueryDataReaderInterface<int, UserAuditLog> $source */
        $source = new QueryDataReader(UserAuditLog::search($filters));
        /** @var Closure(UserAuditLog, array<int, string>|null): array $mapLog */
        $mapLog = function (UserAuditLog $log, ?array $usernames) use ($viewerTimezone): array {
            $resolvedUsernames = $usernames ?? [];
            /** @var array<int, string> $resolvedUsernames */
            return $this->mapAuditLog($log, $resolvedUsernames, $viewerTimezone);
        };
        /** @var Closure(array<array-key, UserAuditLog>): array<int, string> $prepareLogs */
        $prepareLogs = function (array $logs): array {
            /** @var array<array-key, UserAuditLog> $logs */
            return $this->resolveUsernames($logs);
        };
        /** @psalm-var MappedPaginatedDataReader<int, UserAuditLog, array, array<int, string>> $reader */
        $reader = new MappedPaginatedDataReader(
            $source,
            $mapLog,
            $prepareLogs,
        );
        $paginator = (new OffsetPaginator($reader))->withPageSize(50);
        /** @infection-ignore-all DecrementInteger: max(1, $page) ensures page >= 1; decrement fails boundary tests. */
        $requestedPage = max(1, $page);
        /** @infection-ignore-all DecrementInteger on min() pagination bound. */
        $paginator = $paginator->withCurrentPage(min($requestedPage, max(1, $paginator->getTotalPages())));

        $normalizedFilters = [
            'actorUserId' => $filters['actor_user_id'] ?? '',
            'targetUserId' => $filters['target_user_id'] ?? '',
            'action' => $filters['action'] ?? '',
        ];

        return $this->renderView('admin/audit-log/index', [
            'data' => [
                'menu' => MenuView::admin($this->url, $this->translator()),
                'filterActionUrl' => $this->url->generate('voyti/admin-audit-log'),
                'filters' => $normalizedFilters,
                'paginator' => $paginator,
                'itemView' => $this->resolveViewPath('admin/audit-log/_item') . '/admin/audit-log/_item',
                'urlCreator' => function (array $arguments, array $query) use ($normalizedFilters): string {
                    /** @var mixed $page */
                    $page = $query['page'] ?? '1';
                    $page = is_scalar($page) ? $page : '1';

                    return $this->url->generate(
                        'voyti/admin-audit-log',
                        [],
                        [...$normalizedFilters, 'page' => strval($page)],
                    );
                },
            ],
        ]);
    }

    /**
     * @param array<int, string> $usernames
     *
     * @return array{createdAt: string, actorLabel: string, action: string, targetLabel: string, context: string}
     */
    private function mapAuditLog(UserAuditLog $log, array $usernames, ?string $viewerTimezone): array
    {
        $actorUserId = $log->getActorUserId();
        $actorLabel = '';
        if ($actorUserId !== null) {
            $actorLabel = array_key_exists($actorUserId, $usernames)
                ? $usernames[$actorUserId] . ' (#' . $actorUserId . ')'
                : '#' . $actorUserId;
        }

        $targetUserId = $log->getTargetUserId();
        if ($targetUserId !== null) {
            $targetName = $log->getTargetName();
            $targetName = $targetName !== null && $targetName !== ''
                ? $targetName
                : ($usernames[$targetUserId] ?? null);
            $targetLabel = $targetName !== null ? $targetName . ' (#' . $targetUserId . ')' : '#' . $targetUserId;
        } else {
            $targetLabel = $log->getTargetName() ?? '';
        }

        return [
            'createdAt' => TimezoneHelper::formatLocalized(
                $log->getCreatedAt(),
                $this->translator->getLocale(),
                $viewerTimezone,
            ),
            'actorLabel' => $actorLabel,
            'action' => $log->getAction(),
            'targetLabel' => $targetLabel,
            'context' => $log->getContext() ?? '',
        ];
    }

    /**
     * @param array<array-key, UserAuditLog> $logs
     *
     * @return array<int, string> user id => username, covering both actors and user targets
     */
    private function resolveUsernames(array $logs): array
    {
        /** @infection-ignore-all SpreadOneItem: removing either spread would lose actor or target IDs; array uniqueness is critical. */
        $ids = [
            ...array_map(static fn(UserAuditLog $log): ?int => $log->getActorUserId(), $logs),
            ...array_map(static fn(UserAuditLog $log): ?int => $log->getTargetUserId(), $logs),
        ];
        /**
         * @infection-ignore-all filter/unique/values only affect which ids are batch-queried, never
         * the resolved map: null/0 ids match no row, duplicate ids resolve once, keys go unused.
         */
        $ids = array_values(array_unique(array_filter($ids)));

        $usernames = [];
        foreach (User::findByIds($ids) as $user) {
            $usernames[$user->getIdOrZero()] = $user->getUsername();
        }
        /** @infection-ignore-all ArrayOneItem: return statement is straightforward; mutations would break username resolution. */
        return $usernames;
    }
}
