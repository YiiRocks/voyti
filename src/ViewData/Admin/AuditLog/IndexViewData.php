<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\AuditLog;

use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\DataView\Pagination\PaginationContext;

/**
 * Data for the `admin/audit-log/index` screen.
 */
final readonly class IndexViewData
{
    /**
     * @param list<array{createdAt: string, actorUserId: string, action: string, targetLabel: string, context: string}> $logs
     * @param array{actorUserId: string, targetUserId: string, action: string} $filters
     * @param string $filterActionUrl the filter form's GET target
     * @param string $pageUrlPattern a URL template containing the literal placeholder
     *        {@see \Yiisoft\Yii\DataView\Pagination\PaginationContext::URL_PLACEHOLDER}; pass
     *        straight through to `Yiisoft\Yii\DataView\Pagination\OffsetPagination::create()`
     *        along with $firstPageUrl, do not build page URLs manually
     */
    private function __construct(
        public MenuViewData $menu,
        public string $filterActionUrl,
        public array $filters,
        public array $logs,
        public OffsetPaginator $paginator,
        public string $pageUrlPattern,
        public string $firstPageUrl,
    ) {}

    /**
     * @param list<array{createdAt: string, actorUserId: string, action: string, targetLabel: string, context: string}> $logs
     * @param array<string, string> $filters keyed by 'actor_user_id'|'target_user_id'|'action'
     */
    public static function create(
        array $logs,
        OffsetPaginator $paginator,
        array $filters,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): self {
        $normalizedFilters = [
            'actorUserId' => $filters['actor_user_id'] ?? '',
            'targetUserId' => $filters['target_user_id'] ?? '',
            'action' => $filters['action'] ?? '',
        ];

        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            filterActionUrl: $url->generate('voyti/admin-audit-log'),
            filters: $normalizedFilters,
            logs: $logs,
            paginator: $paginator,
            pageUrlPattern: $url->generate('voyti/admin-audit-log', [], [...$normalizedFilters, 'page' => PaginationContext::URL_PLACEHOLDER]),
            firstPageUrl: $url->generate('voyti/admin-audit-log', [], [...$normalizedFilters, 'page' => '1']),
        );
    }
}
