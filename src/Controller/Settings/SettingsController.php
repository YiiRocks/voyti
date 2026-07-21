<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Settings;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Settings\IndexViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Landing page for the `settings/` route group root, showing a welcome summary and a
 * link to every settings screen; a fuller dashboard is planned as separate future work.
 */
final readonly class SettingsController
{
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private ModuleConfig $config,
        private FlashInterface $flash,
        private CurrentUser $currentUser,
    ) {}

    public function index(): ResponseInterface
    {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();

        return $this->renderView('settings/index', [
            'data' => IndexViewData::create($this->config, $this->url, $this->translator(), $user),
        ]);
    }
}
