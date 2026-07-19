<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Auth;

use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Validator\PasswordComplexityRule;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\LabelsProviderInterface;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

/**
 * Backs the password recovery pages, covering both the {@see self::SCENARIO_REQUEST} step
 * (email address) and the {@see self::SCENARIO_RESET} step (new password), each with its own
 * validation rules.
 */
final class RecoveryForm extends FormModel implements LabelsProviderInterface, RulesProviderInterface
{
    public const string SCENARIO_REQUEST = 'request';
    public const string SCENARIO_RESET = 'reset';

    public string $email = '';

    public string $gRecaptchaResponse = '';

    public string $password = '';

    public string $passwordRepeat = '';

    public function __construct(
        private readonly ModuleConfig $config,
        private readonly TranslatorInterface $translator,
        public readonly string $scenario = self::SCENARIO_REQUEST,
    ) {}

    /**
     * @return string
     *
     * @psalm-return 'recovery'
     */
    #[\Override]
    public function getFormName(): string
    {
        return 'recovery';
    }

    /**
     * @return string[]
     *
     * @psalm-return array{email: string, password: string, passwordRepeat: string}
     */
    #[\Override]
    public function getPropertyLabels(): array
    {
        return [
            'email' => $this->translator->translate('voyti.view.email_label', category: 'voyti'),
            'password' => $this->translator->translate('voyti.view.new_password_label', category: 'voyti'),
            'passwordRepeat' => $this->translator->translate('voyti.view.new_password_repeat_label', category: 'voyti'),
        ];
    }

    #[\Override]
    public function getRules(): iterable
    {
        $rules = [];

        if ($this->scenario === self::SCENARIO_REQUEST) {
            $rules['email'] = [
                new Required(),
                new Email(checkDns: true, enableIdn: true, skipOnEmpty: true),
                new Length(max: 255),
            ];
        }

        if ($this->scenario === self::SCENARIO_RESET) {
            $rules['password'] = [
                new Required(),
                new Length(min: 6, max: 72),
                ...PasswordComplexityRule::rules($this->config, $this->translator),
            ];
            $rules['passwordRepeat'] = [
                new Required(),
                new Equal(targetProperty: 'password', strict: true, type: CompareType::STRING),
            ];
        }

        if ($this->scenario === self::SCENARIO_REQUEST) {
            $recaptchaRules = RecaptchaHelper::rules($this->config, $this->getFormName());
            if ($recaptchaRules !== []) {
                $rules['gRecaptchaResponse'] = $recaptchaRules;
            }
        }

        return $rules;
    }

    #[\Override]
    public function getValidationPropertyLabels(): array
    {
        return $this->getPropertyLabels();
    }
}
