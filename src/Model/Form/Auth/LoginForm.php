<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Auth;

use Override;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Helper\ObjectParser;
use Yiisoft\Validator\LabelsProviderInterface;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

/**
 * Backs the login page: username/email, password, and remember-me.
 */
final class LoginForm extends FormModel implements LabelsProviderInterface, RulesProviderInterface
{
    public string $gRecaptchaResponse = '';
    #[Required]
    #[Length(max: 255)]
    public string $login = '';
    public string $password = '';
    public bool $rememberMe = false;

    public function __construct(
        private readonly VoytiConfig $config,
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * @return 'login'
     */
    #[Override]
    public function getFormName(): string
    {
        return 'login';
    }

    /**
     * @return array{login: string, password: string, rememberMe: string}
     */
    #[Override]
    public function getPropertyLabels(): array
    {
        return [
            'login' => $this->translator->translate('voyti.view.login.login_label', category: 'voyti'),
            'password' => $this->translator->translate('voyti.view.login.password_label', category: 'voyti'),
            'rememberMe' => $this->translator->translate('voyti.view.login.remember_me_label', category: 'voyti'),
        ];
    }

    #[Override]
    public function getRules(): iterable
    {
        $parser = new ObjectParser($this);
        $rules = $parser->getRules();

        $rules['password'] = [new Required()];

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
