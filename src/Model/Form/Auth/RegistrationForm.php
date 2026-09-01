<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Auth;

use Override;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\Validator\PasswordComplexityRule;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Helper\ObjectParser;
use Yiisoft\Validator\LabelsProviderInterface;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\TrueValue;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\RulesProviderInterface;

/**
 * Backs the registration page: username, email, password, and personal data processing consent.
 */
final class RegistrationForm extends FormModel implements LabelsProviderInterface, RulesProviderInterface
{
    public bool $dataProcessingConsent = false;
    #[Required]
    #[Email(skipOnEmpty: true)]
    #[Length(max: 255, skipOnEmpty: true)]
    public string $email = '';

    public string $gRecaptchaResponse = '';

    #[Required]
    #[Length(min: 6, max: 72, skipOnEmpty: true)]
    public string $password = '';
    #[Equal(targetProperty: 'password', strict: true, type: CompareType::STRING)]
    public string $passwordRepeat = '';
    #[Required]
    #[Length(min: 3, max: 255, skipOnEmpty: true)]
    #[Regex(pattern: '/^[-a-zA-Z0-9_\.@]+$/', skipOnEmpty: true)]
    public string $username = '';

    public function __construct(
        private readonly VoytiConfig $config,
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * @return 'register'
     */
    #[Override]
    public function getFormName(): string
    {
        return 'register';
    }

    /**
     * @return array{
     *     username: string,
     *     email: string,
     *     password: string,
     *     passwordRepeat: string,
     *     dataProcessingConsent: string,
     * }
     */
    #[Override]
    public function getPropertyLabels(): array
    {
        return [
            'username' => $this->translator->translate('voyti.view.username_label', category: 'voyti'),
            'email' => $this->translator->translate('voyti.view.email_label', category: 'voyti'),
            'password' => $this->translator->translate('voyti.view.password_label', category: 'voyti'),
            'passwordRepeat' => $this->translator->translate('voyti.view.password_repeat_label', category: 'voyti'),
            'dataProcessingConsent' => $this->translator->translate(
                'voyti.view.registration.data_processing_consent_label',
                category: 'voyti',
            ),
        ];
    }

    #[Override]
    public function getRules(): iterable
    {
        $parser = new ObjectParser($this);
        $rules = $parser->getRules();

        /** @var list<RuleInterface> $passwordRules */
        $passwordRules = $rules['password'];
        $rules['password'] = array_merge(
            $passwordRules,
            PasswordComplexityRule::rules($this->config, $this->translator),
        );

        $rules['dataProcessingConsent'] = [new TrueValue(trueValue: true)];

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
