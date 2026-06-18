<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\ViewRenderer;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Validator\ValidatorInterface;
use YiiRocks\Voyti\Form\PermissionForm;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Service\AuthItemEditionService;

final class PermissionController extends AbstractAuthItemController
{
    public function __construct(
        TranslatorInterface $translator,
        ViewRenderer $viewRenderer,
        AuthHelper $authHelper,
        UrlGeneratorInterface $url,
        ValidatorInterface $validator,
        AuthItemEditionService $authItemEditionService,
    ) {
        parent::__construct($translator, $viewRenderer, $authHelper, $url, $validator, $authItemEditionService);
    }

    protected function getItemType(): string
    {
        return 'permission';
    }

    protected function createForm(): AbstractAuthItemForm
    {
        return new PermissionForm();
    }
}
