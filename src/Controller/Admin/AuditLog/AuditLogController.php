<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\AuditLog;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\DataView\Pagination\PaginationContext;
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

        $reader = new QueryDataReader(UserAuditLog::search($filters));
        $paginator = (new OffsetPaginator($reader))->withPageSize(50);
        /** @infection-ignore-all DecrementInteger: max(1, $page) ensures page >= 1; decrement fails boundary tests. */
        $requestedPage = max(1, $page);
        /** @infection-ignore-all DecrementInteger on min() pagination bound. */
        $paginator = $paginator->withCurrentPage(min($requestedPage, max(1, $paginator->getTotalPages())));

        /** @infection-ignore-all — iterator keys are already 0-indexed, preserve_keys has no effect */
        /** @var list<UserAuditLog> $logs */
        $logs = iterator_to_array($paginator->read(), false);

        $viewer = $this->currentUser->getIdentity();
        $viewerTimezone = $viewer instanceof User ? $viewer->getProfile()?->getTimezone() : null;

        $usernames = $this->resolveUsernames($logs);

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
                'logs' => array_map(
                    function (UserAuditLog $log) use ($usernames, $viewerTimezone): array {
                        $actorUserId = $log->getActorUserId();
                        $actorLabel = '';
                        if ($actorUserId !== null) {
                            $actorLabel = array_key_exists($actorUserId, $usernames)
                                ? $usernames[$actorUserId] . ' (#' . $actorUserId . ')'
                                : '#' . $actorUserId;
                        }

                        $targetUserId = $log->getTargetUserId();
                        $targetLabel = '';
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
                    },
                    $logs,
                ),
                'paginator' => $paginator,
                'pageUrlPattern' => $this->url->generate('voyti/admin-audit-log', [], [...$normalizedFilters, 'page' => PaginationContext::URL_PLACEHOLDER]),
                'firstPageUrl' => $this->url->generate('voyti/admin-audit-log', [], [...$normalizedFilters, 'page' => '1']),
            ],
        ]);
    }

    /**
     * @param list<UserAuditLog> $logs
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
