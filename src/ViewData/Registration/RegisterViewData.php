<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Registration;

use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\FormModelInterface;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Data for the `registration/register` screen.
 */
final readonly class RegisterViewData
{
    /**
     * @param string $recaptchaFieldHtml pre-rendered reCAPTCHA widget HTML - echo raw (not
     *        `Html::encode()`); empty string when reCAPTCHA is disabled or the optional
     *        `yiirocks/recaptcha` package isn't installed
     */
    private function __construct(
        public string $formSubmitUrl,
        public string $loginUrl,
        public bool $showGdprConsent,
        public string $recaptchaFieldHtml,
    ) {}

    public static function create(FormModelInterface $form, ModuleConfig $config, UrlGeneratorInterface $url): self
    {
        return new self(
            formSubmitUrl: $url->generate('voyti/registration-register'),
            loginUrl: $url->generate('voyti/session-login'),
            showGdprConsent: $config->enableGdprCompliance,
            recaptchaFieldHtml: RecaptchaHelper::render($form, $config),
        );
    }
}
