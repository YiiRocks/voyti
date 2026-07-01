<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use YiiRocks\Voyti\Form\Rbac\AbstractAuthItemForm;
use YiiRocks\Voyti\Form\Rbac\PermissionForm;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Service\Rbac\ItemEditionService;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class PermissionController extends AbstractAuthItemController
{
    public function __construct(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        AuthHelper $authHelper,
        UrlGeneratorInterface $url,
        ValidatorInterface $validator,
        ItemEditionService $authItemEditionService,
        ResponseFactoryInterface $responseFactory,
    ) {
        parent::__construct($translator, $viewRenderer, $authHelper, $url, $validator, $authItemEditionService, $responseFactory);
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
