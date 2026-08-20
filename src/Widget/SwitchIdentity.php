<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Widget;

use Override;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Csrf\CsrfTokenInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Widget\Widget;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Renders the "you're impersonating this user, click to restore" banner. Host apps can drop this
 * into any template with no setup beyond having voyti installed:
 *
 * ```php
 * <?= SwitchIdentity::widget(); ?>
 * ```
 *
 * Dependencies are resolved through the DI container by {@see Widget::widget()}. Renders an
 * empty string if the current user isn't impersonating anyone. Markup lives in the installed
 * views package (`shared/_switch-identity`), not here - see {@see RenderTrait}.
 */
final class SwitchIdentity extends Widget
{
    use RenderTrait;

    public function __construct(
        private readonly CsrfTokenInterface $csrfToken,
        private readonly SwitchIdentityService $switchIdentityService,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $url,
        private readonly VoytiConfig $config,
        private readonly WebViewRenderer $viewRenderer,
    ) {}

    #[Override]
    public function render(): string
    {
        if (!$this->switchIdentityService->isSwitched()) {
            /** @infection-ignore-all Equivalent: not-switched means no original-user id in session, so removing this return falls through to getOriginalUser() returning null and the same empty-string return below. */
            return '';
        }

        $originalUser = $this->switchIdentityService->getOriginalUser();
        if ($originalUser === null) {
            return '';
        }

        return (string) $this->renderFragment('shared/_switch-identity', [
            'data' => [
                'message' => $this->translator->translate(
                    'voyti.view.admin.impersonating_banner',
                    ['username' => $originalUser->getUsername()],
                    category: 'voyti',
                ),
                'restoreUrl' => $this->url->generate('voyti/admin-users-switch-identity-restore'),
                'restoreButtonLabel' => $this->translator->translate('voyti.view.admin.restore_button', category: 'voyti'),
                'csrfToken' => $this->csrfToken->getValue(),
            ],
        ])->getBody();
    }
}
