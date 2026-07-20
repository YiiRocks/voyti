<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Session;

use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Shared\SocialConnectViewData;
use Yiisoft\FormModel\FormModelInterface;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Data for the `session/login` screen.
 */
final readonly class LoginViewData
{
    /**
     * @param string $recaptchaFieldHtml pre-rendered reCAPTCHA widget HTML - echo raw (not
     *        `Html::encode()`); empty string when reCAPTCHA is disabled or the optional
     *        `yiirocks/recaptcha` package isn't installed
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
        ModuleConfig $config,
        UrlGeneratorInterface $url,
        AuthClientRegistry $authClients,
    ): self {
        return new self(
            formSubmitUrl: $url->generate('voyti/session-login'),
            forgotPasswordUrl: $url->generate('voyti/password-reset-request'),
            showRegisterLink: $config->enableRegistration,
            registerUrl: $url->generate('voyti/registration-register'),
            recaptchaFieldHtml: RecaptchaHelper::render($form, $config),
            connect: SocialConnectViewData::create($authClients, $url),
        );
    }
}
