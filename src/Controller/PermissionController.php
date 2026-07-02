<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use YiiRocks\Voyti\Form\Rbac\AbstractAuthItemForm;
use YiiRocks\Voyti\Form\Rbac\PermissionForm;
use YiiRocks\Voyti\Repository\UserRepository;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class PermissionController extends AbstractAuthItemController
{
    public function __construct(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        UrlGeneratorInterface $url,
        ValidatorInterface $validator,
        ResponseFactoryInterface $responseFactory,
        UserRepository $userRepository,
        ItemsStorageInterface $itemsStorage,
        ManagerInterface $managerInterface,
        AssignmentsStorageInterface $assignmentsStorage,
    ) {
        parent::__construct($translator, $viewRenderer, $url, $validator, $responseFactory, $userRepository, $itemsStorage, $managerInterface, $assignmentsStorage);
    }

    /**
     * @return PermissionForm
     */
    #[\Override]
    protected function createForm(): AbstractAuthItemForm
    {
        return new PermissionForm($this->translator);
    }

    /**
     * @return string
     *
     * @psalm-return 'permission'
     */
    #[\Override]
    protected function getItemType(): string
    {
        return 'permission';
    }

    #[\Override]
    protected function getIndexRouteName(): string
    {
        return 'permissions';
    }
}
