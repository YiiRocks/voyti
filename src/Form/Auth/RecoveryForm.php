<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Auth;

use YiiRocks\Recaptcha\RecaptchaV2Rule;
use YiiRocks\Recaptcha\RecaptchaV3Rule;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Helper\ObjectParser;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

final class RecoveryForm extends FormModel implements RulesProviderInterface
{
    public const SCENARIO_REQUEST = 'request';
    public const SCENARIO_RESET = 'reset';

    #[Required]
    #[Email]
    public string $email = '';

    public string $gRecaptchaResponse = '';

    #[Required]
    #[Length(min: 6, max: 72)]
    public string $password = '';

    public function __construct(
        private readonly ModuleConfig $config,
        private readonly TranslatorInterface $translator,
        public readonly string $scenario = self::SCENARIO_REQUEST,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getAttributeLabels(): array
    {
        return [
            'email' => $this->translator->translate('voyti.view.email_label', category: 'voyti'),
            'password' => $this->translator->translate('voyti.view.new_password_label', category: 'voyti'),
        ];
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
    public function getFormName(): string
    {
        return 'recovery';
    }

    #[\Override]
    public function getRules(): iterable
    {
        $parser = new ObjectParser($this);
        $rules = $parser->getRules();

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
