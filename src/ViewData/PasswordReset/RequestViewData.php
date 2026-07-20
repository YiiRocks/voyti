<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\PasswordReset;

use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\FormModelInterface;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Data for the `password-reset/request` screen.
 */
final readonly class RequestViewData
{
    /**
     * @param string $recaptchaFieldHtml pre-rendered reCAPTCHA widget HTML - echo raw (not
     *        `Html::encode()`); empty string when reCAPTCHA is disabled or the optional
     *        `yiirocks/recaptcha` package isn't installed
     */
    private function __construct(
        public string $formSubmitUrl,
        public string $loginUrl,
        public string $recaptchaFieldHtml,
    ) {}

    public static function create(FormModelInterface $form, ModuleConfig $config, UrlGeneratorInterface $url): self
    {
        return new self(
            formSubmitUrl: $url->generate('voyti/password-reset-request'),
            loginUrl: $url->generate('voyti/session-login'),
            recaptchaFieldHtml: RecaptchaHelper::render($form, $config),
        );
    }
}
