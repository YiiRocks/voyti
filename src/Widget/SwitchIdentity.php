<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Widget;

use Override;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use Yiisoft\Csrf\CsrfTokenInterface;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Widget\Widget;

/**
 * Renders the "you're impersonating this user, click to restore" banner. Host apps can drop this
 * into any template with no setup beyond having voyti installed:
 *
 * ```php
 * <?= SwitchIdentity::widget(); ?>
 * ```
 *
 * Dependencies are resolved through the DI container by {@see Widget::widget()}. Renders an
 * empty string if the current user isn't impersonating anyone.
 */
final class SwitchIdentity extends Widget
{
    public function __construct(
        private readonly CsrfTokenInterface $csrfToken,
        private readonly SwitchIdentityService $switchIdentityService,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $url,
    ) {}

    #[Override]
    public function render(): string
    {
        if (!$this->switchIdentityService->isSwitched()) {
            return '';
        }

        $originalUser = $this->switchIdentityService->getOriginalUser();
        if ($originalUser === null) {
            return '';
        }

        $message = $this->translator->translate(
            'voyti.view.admin.impersonating_banner',
            ['username' => $originalUser->getUsername()],
            category: 'voyti',
        );
        $restoreUrl = $this->url->generate('voyti/admin-users-switch-identity-restore');
        $restoreButtonLabel = $this->translator->translate('voyti.view.admin.restore_button', category: 'voyti');

        $html = Html::div()->class('alert alert-warning d-flex justify-content-between align-items-center')->open();
        $html .= Html::span($message)->render();
        $html .= Html::form()->post($restoreUrl)->csrf($this->csrfToken->getValue())->open();
        $html .= Html::submitButton($restoreButtonLabel)->class('btn', 'btn-warning', 'btn-sm')->render();
        $html .= Html::form()->close();
        $html .= Html::div()->close();

        return $html;
    }
}
