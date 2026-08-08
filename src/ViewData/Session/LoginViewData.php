<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Session;

use YiiRocks\Voyti\Helper\LinkButtonHelper;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormModelInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Widget\AuthChoice;

/**
 * Data for the `session/login` screen.
 */
final readonly class LoginViewData
{
    /**
     * @param string $recaptchaFieldHtml pre-rendered reCAPTCHA widget HTML - echo raw (not
     *        `Html::encode()`); empty string when reCAPTCHA is disabled or the optional
     *        `yiirocks/recaptcha` package isn't installed
     * @param AuthChoice|null $authChoice social login widget - null when
     *        `yiisoft/yii-auth-client` isn't installed
     */
    private function __construct(
        public string $formSubmitUrl,
        public string $forgotPasswordUrl,
        public bool $showRegisterLink,
        public string $registerUrl,
        public string $recaptchaFieldHtml,
        public ?AuthChoice $authChoice,
    ) {}

    public static function create(
        FormModelInterface $form,
        VoytiConfig $config,
        UrlGeneratorInterface $url,
        ?Collection $clientCollection,
    ): self {
        $authChoice = null;
        if ($clientCollection !== null) {
            /** @infection-ignore-all The auth-choice widget (route + cosmetic button styling) only renders when the host has configured OAuth clients, so its construction has no behavioural effect the library's own suite can observe. */
            $authChoice = AuthChoice::widget()
                ->authRoute('voyti/session-auth')
                ->linkAttributes(['class' => LinkButtonHelper::submitButtonClass()]);
        }

        return new self(
            formSubmitUrl: $url->generate('voyti/session-login'),
            forgotPasswordUrl: $url->generate('voyti/password-reset-request'),
            showRegisterLink: $config->enableRegistration,
            registerUrl: $url->generate('voyti/registration-register'),
            recaptchaFieldHtml: RecaptchaHelper::render($form, $config),
            authChoice: $authChoice,
        );
    }
}
