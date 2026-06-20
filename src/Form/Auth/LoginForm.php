<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Auth;

use YiiRocks\Recaptcha\RecaptchaV2Rule;
use YiiRocks\Recaptcha\RecaptchaV3Rule;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Helper\ObjectParser;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

final class LoginForm extends FormModel implements RulesProviderInterface
{

    public string $gRecaptchaResponse = '';
    #[Required]
    public string $login = '';
    #[Required]
    public string $password = '';
    public bool $rememberMe = false;
    public ?string $twoFactorAuthenticationCode = null;

    public function __construct(
        private readonly ModuleConfig $config,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getAttributeLabels(): array
    {
        return [
            'login' => $this->translator->translate('voyti.view.login.login_label', category: 'voyti'),
            'password' => $this->translator->translate('voyti.view.password_label', category: 'voyti'),
            'rememberMe' => $this->translator->translate('voyti.view.login.remember_me', category: 'voyti'),
            'twoFactorAuthenticationCode' => $this->translator->translate('voyti.view.two_factor.code_label', category: 'voyti'),
        ];
    }

    #[\Override]
    public function getFormName(): string
    {
        return 'login';
    }

    #[\Override]
    public function getRules(): iterable
    {
        $parser = new ObjectParser($this);
        $rules = $parser->getRules();

        if ($this->config->recaptchaVersion !== null && class_exists(RecaptchaV3Rule::class)) {
            $ruleClass = $this->config->recaptchaVersion === 'v2'
                ? RecaptchaV2Rule::class
                : RecaptchaV3Rule::class;

            $params = [];
            if ($this->config->recaptchaVersion === 'v3') {
                $params['threshold'] = 0.5;
                $params['action'] = 'voyti_' . $this->getFormName();
            }

            $rules['gRecaptchaResponse'] = [new $ruleClass(...$params)];
        }

        return $rules;
    }
}
