<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\User;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
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
     * @param string $createUserUrl a link (GET) to the create-user screen, not a form target
     * @param string $filterActionUrl the filter form's GET target
     * @param string|null $switchedBannerMessage set only when an admin is currently impersonating
     *        another user (see {@see \YiiRocks\Voyti\Service\SwitchIdentityService}); pair with
     *        $formSubmitUrl to offer a "restore my identity" action
     * @param string $formSubmitUrl the "restore original identity" form's POST target - not
     *        related to $users/creating/updating a user
     * @param string $pageUrlPattern a URL template containing the literal placeholder
     *        {@see \Yiisoft\Yii\DataView\Pagination\PaginationContext::URL_PLACEHOLDER}; pass
     *        straight through to `Yiisoft\Yii\DataView\Pagination\OffsetPagination::create()`
     *        along with $firstPageUrl, do not build page URLs manually
     */
    private function __construct(
        public MenuViewData $menu,
        public string $createUserUrl,
        public string $filterActionUrl,
        public array $filters,
        public ?string $switchedBannerMessage,
        public string $formSubmitUrl,
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
        ModuleConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
        bool $isSwitched,
        ?User $originalUser,
        int $currentUserId,
    ): self {
        $normalizedFilters = [
            'username' => $filters['username'] ?? '',
            'email' => $filters['email'] ?? '',
            'status' => $filters['status'] ?? '',
        ];

        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            createUserUrl: $url->generate('voyti/admin-users-create'),
            filterActionUrl: $url->generate('voyti/admin-users'),
            filters: $normalizedFilters,
            switchedBannerMessage: $isSwitched && $originalUser !== null
                ? $translator->translate('voyti.view.admin.switched_banner', ['username' => $originalUser->getUsername()])
                : null,
            formSubmitUrl: $url->generate('voyti/admin-users-switch-identity-restore'),
            users: array_map(
                static fn(User $user): UserRow => UserRow::create($user, $config, $url, $translator, $isSwitched, $currentUserId),
                $users,
            ),
            paginator: $paginator,
            pageUrlPattern: $url->generate('voyti/admin-users', [], [...$normalizedFilters, 'page' => PaginationContext::URL_PLACEHOLDER]),
            firstPageUrl: $url->generate('voyti/admin-users', [], [...$normalizedFilters, 'page' => '1']),
        );
    }
}
