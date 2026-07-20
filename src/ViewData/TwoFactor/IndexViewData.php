<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\TwoFactor;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `two-factor/index` screen (also reused, via {@see self::$emailSetup}/{@see self::$googleSetup},
 * to preload the setup fragment inline instead of loading it by AJAX).
 */
final readonly class IndexViewData
{
    /**
     * @param array<string, list<string>> $errors
     * @param bool $isEnabled whether 2FA is currently turned on for this user
     * @param string $method 'email' or 'google' - the selected/active method, independent of $isEnabled
     * @param string $enabledWithMethodMessage already-translated, states which method is enabled; only
     *        meaningful when $isEnabled
     * @param bool $emailCodeSent whether a disable-confirmation code was already emailed in this flow;
     *        when true, $disableUrl expects that code instead of $disableSendCodeUrl being usable
     * @param string $disableSendCodeUrl POST target to email a disable-confirmation code (email method
     *        only, before $emailCodeSent)
     * @param string $disableUrl POST target that actually disables 2FA, given a code or backup code
     * @param bool $hasBackupCodes whether the user currently has unused backup codes
     * @param string $regenerateBackupCodesUrl POST target to regenerate backup codes
     * @param string $googleUrl a link (GET) for the "Google/TOTP" method-switch button; also used as
     *        a JS fetch target to lazy-load that method's setup fragment
     * @param string $emailUrl a link (GET) for the "Email" method-switch button; also used as a JS
     *        fetch target to lazy-load that method's setup fragment
     * @param bool $preloadContent whether $emailSetup/$googleSetup (matching $method) is already
     *        populated for inline rendering, vs. left for the page's own JS to lazy-load via $autoloadUrl
     * @param string $renewUrl fetch(POST) target the page's own JS calls to regenerate the QR
     *        code/secret without a full reload
     * @param string $renewErrorMessage already-translated, used only by the page's own JS on a failed
     *        $renewUrl call
     * @param string|null $autoloadUrl JS fetch target for the page to load on page-load when
     *        $preloadContent is false; null when the fragment was already rendered inline
     * @param EmailSetupViewData|null $emailSetup populated only when $preloadContent && $method === 'email'
     * @param GoogleSetupViewData|null $googleSetup populated only when $preloadContent && $method === 'google'
     */
    private function __construct(
        public MenuViewData $menu,
        public array $errors,
        public bool $isEnabled,
        public string $method,
        public string $enabledWithMethodMessage,
        public bool $emailCodeSent,
        public string $disableSendCodeUrl,
        public string $disableUrl,
        public bool $hasBackupCodes,
        public string $regenerateBackupCodesUrl,
        public string $googleUrl,
        public string $emailUrl,
        public bool $preloadContent,
        public string $renewUrl,
        public string $renewErrorMessage,
        public ?string $autoloadUrl,
        public ?EmailSetupViewData $emailSetup,
        public ?GoogleSetupViewData $googleSetup,
    ) {}

    /**
     * @param array<string, list<string>> $errors
     */
    public static function create(
        User $user,
        string $method,
        array $errors,
        string $qrCodeUri,
        ?string $secret,
        bool $emailCodeSent,
        bool $hasBackupCodes,
        bool $preloadContent,
        ModuleConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): self {
        $isEnabled = $user->isAuthTfEnabled();
        $methodName = $translator->translate($method === 'email' ? 'voyti.view.two_factor_email.method_name' : 'voyti.view.two_factor_google.button_label');

        $emailSetup = null;
        $googleSetup = null;
        if (!$isEnabled && $preloadContent) {
            if ($method === 'email') {
                $emailSetup = EmailSetupViewData::create($user, $emailCodeSent, $url);
            } else {
                $googleSetup = GoogleSetupViewData::create($qrCodeUri, $secret, $url, $translator);
            }
        }

        $googleUrl = $url->generate('voyti/two-factor-google');
        $emailUrl = $url->generate('voyti/two-factor-email');

        return new self(
            menu: MenuViewData::forAccount($config, $url, $translator),
            errors: $errors,
            isEnabled: $isEnabled,
            method: $method,
            enabledWithMethodMessage: $translator->translate('voyti.view.two_factor.enabled_with_method', ['method' => $methodName]),
            emailCodeSent: $emailCodeSent,
            disableSendCodeUrl: $url->generate('voyti/two-factor-disable-send-code'),
            disableUrl: $url->generate('voyti/two-factor-disable'),
            hasBackupCodes: $hasBackupCodes,
            regenerateBackupCodesUrl: $url->generate('voyti/two-factor-regenerate-backup-codes'),
            googleUrl: $googleUrl,
            emailUrl: $emailUrl,
            preloadContent: $preloadContent,
            renewUrl: $url->generate('voyti/two-factor-renew'),
            renewErrorMessage: $translator->translate('voyti.view.two_factor.renew_error'),
            autoloadUrl: $preloadContent ? null : ($method === 'email' ? $emailUrl : $googleUrl),
            emailSetup: $emailSetup,
            googleSetup: $googleSetup,
        );
    }
}
