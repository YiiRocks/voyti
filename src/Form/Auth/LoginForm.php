<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Auth;

use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Helper\ObjectParser;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

final class LoginForm extends FormModel implements RulesProviderInterface
{
    public string $gRecaptchaResponse = '';
    #[Required]
    #[Length(max: 255)]
    public string $login = '';
    #[Required]
    public string $password = '';
    public bool $rememberMe = false;
    public ?string $twoFactorAuthenticationCode = null;

    public function __construct(
        private readonly ModuleConfig $config,
        private readonly TranslatorInterface $translator,
        private readonly bool $requireTwoFactorAuthenticationCode = false,
    ) {
    }

    /**
     * @return string[]
     *
     * @psalm-return array{login: string, password: string, rememberMe: string, twoFactorAuthenticationCode: string}
     */
    public function getAttributeLabels(): array
    {
        return [
            'login' => $this->translator->translate('voyti.view.login.login_label', category: 'voyti'),
            'password' => $this->translator->translate('voyti.view.login.password_label', category: 'voyti'),
            'rememberMe' => $this->translator->translate('voyti.view.login.remember_me_label', category: 'voyti'),
            'twoFactorAuthenticationCode' => $this->translator->translate(
                'voyti.view.two_factor.code_label',
                category: 'voyti',
            ),
        ];
    }

    /**
     * @return string
     *
     * @psalm-return 'login'
     */
    #[\Override]
    public function getFormName(): string
    {
        return 'login';
    }

    #[\Override]
    public function getPropertyLabels(): array
    {
        return $this->getAttributeLabels();
    }

    #[\Override]
    public function getRules(): iterable
    {
        $parser = new ObjectParser($this);
        $rules = $parser->getRules();

        if ($this->requireTwoFactorAuthenticationCode) {
            $rules['twoFactorAuthenticationCode'] = [new Required(), new Integer(), new Length(exactly: 6)];
        }

        $recaptchaRules = RecaptchaHelper::rules($this->config, $this->getFormName());
        if ($recaptchaRules !== []) {
            $rules['gRecaptchaResponse'] = $recaptchaRules;
        }

        return $rules;
    }
}
