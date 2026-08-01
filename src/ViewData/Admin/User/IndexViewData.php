<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\User;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\DataView\Pagination\PaginationContext;

/**
 * Data for the `admin/user/index` screen.
 */
final readonly class IndexViewData
{
    /**
     * @param list<UserRow> $users
     * @param array{username: string, email: string, status: string} $filters
     * @param int $perPage the effective (already clamped) page size, echoed back so the view can
     *        preselect it in the per-page dropdown and pagination links can preserve it
     * @param string $createUserUrl a link (GET) to the create-user screen, not a form target
     * @param string $filterActionUrl the filter form's GET target
     * @param string $pageUrlPattern a URL template containing the literal placeholder
     *        {@see PaginationContext::URL_PLACEHOLDER}; pass
     *        straight through to `Yiisoft\Yii\DataView\Pagination\OffsetPagination::create()`
     *        along with $firstPageUrl, do not build page URLs manually
     */
    private function __construct(
        public MenuViewData $menu,
        public string $createUserUrl,
        public string $filterActionUrl,
        public array $filters,
        public int $perPage,
        public array $users,
        public OffsetPaginator $paginator,
        public string $pageUrlPattern,
        public string $firstPageUrl,
    ) {}

    /**
     * @param list<User> $users
     * @param array<string, string> $filters keyed by 'username'|'email'|'status'
     */
    public static function create(
        array $users,
        OffsetPaginator $paginator,
        array $filters,
        int $perPage,
        VoytiConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
        bool $isSwitched,
        int $currentUserId,
    ): self {
        $normalizedFilters = [
            'username' => $filters['username'] ?? '',
            'email' => $filters['email'] ?? '',
            'status' => $filters['status'] ?? '',
        ];
        $preservedQuery = [...$normalizedFilters, 'perPage' => (string) $perPage];

        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            createUserUrl: $url->generate('voyti/admin-users-create'),
            filterActionUrl: $url->generate('voyti/admin-users'),
            filters: $normalizedFilters,
            perPage: $perPage,
            users: array_map(
                static fn(User $user): UserRow => UserRow::create($user, $config, $url, $translator, $isSwitched, $currentUserId),
                $users,
            ),
            paginator: $paginator,
            pageUrlPattern: $url->generate('voyti/admin-users', [], [...$preservedQuery, 'page' => PaginationContext::URL_PLACEHOLDER]),
            firstPageUrl: $url->generate('voyti/admin-users', [], [...$preservedQuery, 'page' => '1']),
        );
    }
}
