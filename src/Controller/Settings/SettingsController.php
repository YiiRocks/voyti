<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Settings;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\UrlGeneratorInterface;
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
        private VoytiConfig $config,
        private FlashNotifier $flashNotifier,
        private CurrentUser $currentUser,
    ) {}

    public function index(): ResponseInterface
    {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();
        $profile = $user->getProfile();

        return $this->renderView('settings/index', [
            'data' => [
                'menu' => MenuView::account($this->config, $this->url, $this->translator()),
                'displayName' => $profile?->getName() ?? $user->getUsername(),
                'email' => $user->getEmail(),
                'memberSinceDisplay' => TimezoneHelper::formatLocalized(
                    $user->getCreatedAt(),
                    $this->translator()->getLocale(),
                    $profile?->getTimezone(),
                ),
            ],
        ]);
    }
}
