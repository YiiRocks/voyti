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
use YiiRocks\Voyti\Model\AuditLog;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

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
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $this->queryParams($request);
        $filters = [
            'actor_user_id' => $this->stringValue($queryParams, 'actorUserId'),
            'target_user_id' => $this->stringValue($queryParams, 'targetUserId'),
            'action' => $this->stringValue($queryParams, 'action'),
        ];

        $reader = new QueryDataReader(AuditLog::search($filters));
        $paginator = (new OffsetPaginator($reader))->withPageSize(50);
        $requestedPage = max(1, (int) ($queryParams['page'] ?? 1));
        $paginator = $paginator->withCurrentPage(min($requestedPage, max(1, $paginator->getTotalPages())));

        /** @var list<AuditLog> $logs */
        $logs = iterator_to_array($paginator->read(), false);

        return $this->renderView('admin/audit-log/index', [
            'logs' => array_map(fn (AuditLog $log): array => $this->presentLog($log), $logs),
            'paginator' => $paginator,
            'filters' => $filters,
            'flash' => $this->flash,
        ]);
    }

    /**
     * @return array{createdAt: string, actorUserId: string, action: string, targetLabel: string, context: string}
     */
    private function presentLog(AuditLog $log): array
    {
        return [
            'createdAt' => TimezoneHelper::formatLocalized($log->getCreatedAt(), $this->translator->getLocale()),
            'actorUserId' => (string) ($log->getActorUserId() ?? ''),
            'action' => $log->getAction(),
            'targetLabel' => $this->targetLabel($log),
            'context' => $log->getContext() ?? '',
        ];
    }

    private function targetLabel(AuditLog $log): string
    {
        $name = $log->getTargetName() ?? '';
        $userId = $log->getTargetUserId();

        return $userId !== null ? $name . ' (#' . $userId . ')' : $name;
    }

}
