<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Auth;

use Override;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Helper\ObjectParser;
use Yiisoft\Validator\LabelsProviderInterface;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

/**
 * Backs the login page: username/email, password, remember-me, and optionally a two-factor code.
 */
final class LoginForm extends FormModel implements LabelsProviderInterface, RulesProviderInterface
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
    ) {}

    /**
     * @return string
     *
     * @psalm-return 'login'
     */
    #[Override]
    public function getFormName(): string
    {
        return 'login';
    }

    /**
     * @return string[]
     *
     * @psalm-return array{login: string, password: string, rememberMe: string, twoFactorAuthenticationCode: string}
     */
    #[Override]
    public function getPropertyLabels(): array
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

    #[Override]
    public function getRules(): iterable
    {
        $parser = new ObjectParser($this);
        $rules = $parser->getRules();

        if ($this->requireTwoFactorAuthenticationCode) {
            // Accepts either a 6-digit TOTP/email code or an alphanumeric backup code
            // (SessionController::confirm() falls back to BackupCodeService::consume()),
            // so no format-specific rule can be enforced here beyond presence.
            $rules['twoFactorAuthenticationCode'] = [new Required()];
        }

        $recaptchaRules = RecaptchaHelper::rules($this->config, $this->getFormName());
        if ($recaptchaRules !== []) {
            $rules['gRecaptchaResponse'] = $recaptchaRules;
        }

        return $rules;
    }

    #[Override]
    public function getValidationPropertyLabels(): array
    {
        return $this->getPropertyLabels();
    }
}
