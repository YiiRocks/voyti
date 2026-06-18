<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\ViewInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Validator\ValidatorInterface;
use YiiRocks\Voyti\Form\PermissionForm;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Service\AuthItemEditionService;

final class PermissionController extends AbstractAuthItemController
{
    public function __construct(
        TranslatorInterface $translator,
        ViewInterface $view,
        ResponseFactoryInterface $responseFactory,
        Aliases $aliases,
        AuthHelper $authHelper,
        UrlGeneratorInterface $url,
        ValidatorInterface $validator,
        AuthItemEditionService $authItemEditionService,
    ) {
        parent::__construct($translator, $view, $responseFactory, $aliases, $authHelper, $url, $validator, $authItemEditionService);
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
