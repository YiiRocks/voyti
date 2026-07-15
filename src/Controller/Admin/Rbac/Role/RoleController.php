<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\Rbac\Role;

use YiiRocks\Voyti\Controller\Admin\Rbac\AbstractAuthItemController;

final readonly class RoleController extends AbstractAuthItemController
{
    public function __construct(
        \Yiisoft\Translator\TranslatorInterface $translator,
        \Yiisoft\Yii\View\Renderer\WebViewRenderer $viewRenderer,
        \Yiisoft\Router\UrlGeneratorInterface $url,
        \Yiisoft\Validator\ValidatorInterface $validator,
        \Psr\Http\Message\ResponseFactoryInterface $responseFactory,
        \Yiisoft\Rbac\ItemsStorageInterface $itemsStorage,
        \Yiisoft\Rbac\ManagerInterface $managerInterface,
        \Yiisoft\Rbac\AssignmentsStorageInterface $assignmentsStorage,
        \Yiisoft\Session\Flash\FlashInterface $flash,
        \YiiRocks\Voyti\ModuleConfig $config,
        \YiiRocks\Voyti\Service\AuditLogService $auditLogService,
        \Yiisoft\User\CurrentUser $currentUser,
    ) {
        parent::__construct(
            $translator,
            $viewRenderer,
            $url,
            $validator,
            $responseFactory,
            $itemsStorage,
            $managerInterface,
            $assignmentsStorage,
            $flash,
            $config,
            $auditLogService,
            $currentUser,
            'role',
            'admin-rbac-roles',
        );
    }
}
