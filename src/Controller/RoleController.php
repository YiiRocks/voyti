<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use YiiRocks\Voyti\Form\Rbac\AbstractAuthItemForm;
use YiiRocks\Voyti\Form\Rbac\RoleForm;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Service\Rbac\ItemEditionService;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final class RoleController extends AbstractAuthItemController
{
    public function __construct(
        TranslatorInterface $translator,
        WebViewRenderer $viewRenderer,
        AuthHelper $authHelper,
        UrlGeneratorInterface $url,
        ValidatorInterface $validator,
        ItemEditionService $authItemEditionService,
    ) {
        parent::__construct($translator, $viewRenderer, $authHelper, $url, $validator, $authItemEditionService);
    }

    #[\Override]
    protected function createForm(): AbstractAuthItemForm
    {
        return new RoleForm($this->translator);
    }

    #[\Override]
    protected function getItemType(): string
    {
        return 'role';
    }
}
