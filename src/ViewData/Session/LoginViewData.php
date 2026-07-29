<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Session;

use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ViewData\Shared\SocialConnectViewData;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormModelInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\AuthClient\Collection;

/**
 * Data for the `session/login` screen.
 */
final readonly class LoginViewData
{
    /**
     * @param string $recaptchaFieldHtml pre-rendered reCAPTCHA widget HTML - echo raw (not
     *        `Html::encode()`); empty string when reCAPTCHA is disabled or the optional
     *        `yiirocks/recaptcha` package isn't installed
     * @param SocialConnectViewData $connect empty provider list when no providers are configured
     *        or the optional `yiisoft/yii-auth-client` package isn't installed
     */
    private function __construct(
        public string $formSubmitUrl,
        public string $forgotPasswordUrl,
        public bool $showRegisterLink,
        public string $registerUrl,
        public string $recaptchaFieldHtml,
        public SocialConnectViewData $connect,
    ) {}

    public static function create(
        FormModelInterface $form,
        VoytiConfig $config,
        UrlGeneratorInterface $url,
        ?Collection $clientCollection,
    ): self {
        return new self(
            formSubmitUrl: $url->generate('voyti/session-login'),
            forgotPasswordUrl: $url->generate('voyti/password-reset-request'),
            showRegisterLink: $config->enableRegistration,
            registerUrl: $url->generate('voyti/registration-register'),
            recaptchaFieldHtml: RecaptchaHelper::render($form, $config),
            connect: SocialConnectViewData::create($clientCollection, $url),
        );
    }
}
