<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Auth;

use YiiRocks\Recaptcha\RecaptchaV2Rule;
use YiiRocks\Recaptcha\RecaptchaV3Rule;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

final class RecoveryForm extends FormModel implements RulesProviderInterface
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
    ) {
    }

    /**
     * @return string[]
     *
     * @psalm-return array{email: string, password: string, passwordRepeat: string}
     */
    public function getAttributeLabels(): array
    {
        return [
            'email' => $this->translator->translate('voyti.view.email_label', category: 'voyti'),
            'password' => $this->translator->translate('voyti.view.new_password_label', category: 'voyti'),
            'passwordRepeat' => $this->translator->translate('voyti.view.new_password_repeat_label', category: 'voyti'),
        ];
    }

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

    #[\Override]
    public function getPropertyLabel(string $property): string
    {
        /** @var array<string, string> $labels */
        $labels = $this->getAttributeLabels();
        if (isset($labels[$property])) {
            return $labels[$property];
        }
        return parent::getPropertyLabel($property);
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
            ];
            $rules['passwordRepeat'] = [
                new Required(),
                new Equal(targetProperty: 'password', strict: true, type: CompareType::STRING),
            ];
        }

        if ($this->config->recaptchaVersion !== null
            && $this->scenario === self::SCENARIO_REQUEST
            && class_exists(RecaptchaV3Rule::class)
        ) {
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
