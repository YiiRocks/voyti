<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Settings;

use DateTimeImmutable;
use YiiRocks\Voyti\Helper\AgeHelper;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\UserProfile;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\LabelsProviderInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\Rule\Callback;
use Yiisoft\Validator\Rule\Date\Date;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Url;

/**
 * Backs the profile settings page. Converts to/from a {@see UserProfile} via
 * {@see self::fromProfile()}/{@see self::applyToProfile()}, and carries its own callback
 * validators (birthday not in the future, no HTML tags, valid timezone).
 */
final class UserProfileForm extends FormModel implements LabelsProviderInterface
{
    #[Callback(method: 'validateNoHtmlTags', skipOnEmpty: true)]
    #[Length(max: 65535)]
    public string $bio = '';
    #[Callback(method: 'validateBirthdayNotInFuture', skipOnEmpty: true)]
    #[Date(format: 'php:Y-m-d', skipOnEmpty: true)]
    public string $birthday = '';
    #[Email(checkDns: true, enableIdn: true, skipOnEmpty: true)]
    #[Length(max: 255)]
    public string $gravatarEmail = '';
    #[Callback(method: 'validateNoHtmlTags', skipOnEmpty: true)]
    #[Length(max: 255)]
    public string $location = '';
    #[Callback(method: 'validateNoHtmlTags', skipOnEmpty: true)]
    #[Length(max: 255)]
    public string $name = '';
    #[Email(checkDns: true, enableIdn: true, skipOnEmpty: true)]
    #[Length(max: 255)]
    public string $publicEmail = '';
    #[Callback(method: 'validateTimezone', skipOnEmpty: true)]
    public string $timezone = '';
    #[Length(max: 255)]
    #[Url(skipOnEmpty: true)]
    public string $website = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function applyToProfile(UserProfile $profile): void
    {
        $profile->setName($this->name !== '' ? $this->name : null);
        $profile->setPublicEmail($this->publicEmail !== '' ? $this->publicEmail : null);
        $profile->setGravatarEmail($this->gravatarEmail !== '' ? $this->gravatarEmail : null);
        $profile->setLocation($this->location !== '' ? $this->location : null);
        $profile->setWebsite($this->website !== '' ? $this->website : null);
        $profile->setTimezone($this->timezone !== '' ? $this->timezone : null);
        $profile->setBio($this->bio !== '' ? $this->bio : null);
        $profile->setBirthday($this->birthday !== '' ? new DateTimeImmutable($this->birthday) : null);
    }

    public static function fromProfile(UserProfile $profile, TranslatorInterface $translator): self
    {
        $form = new self($translator);
        $form->name = $profile->getName() ?? '';
        $form->publicEmail = $profile->getPublicEmail() ?? '';
        $form->gravatarEmail = $profile->getGravatarEmail() ?? '';
        $form->location = $profile->getLocation() ?? '';
        $form->website = $profile->getWebsite() ?? '';
        $form->timezone = $profile->getTimezone() ?? '';
        $form->bio = $profile->getBio() ?? '';
        $form->birthday = $profile->getBirthday()?->format('Y-m-d') ?? '';

        return $form;
    }

    /**
     * @return string
     *
     * @psalm-return 'userProfile'
     */
    #[\Override]
    public function getFormName(): string
    {
        return 'userProfile';
    }

    /**
     * @return string[]
     *
     * @psalm-return array{
     *     name: string,
     *     publicEmail: string,
     *     gravatarEmail: string,
     *     location: string,
     *     website: string,
     *     timezone: string,
     *     bio: string,
     *     birthday: string,
     * }
     */
    #[\Override]
    public function getPropertyLabels(): array
    {
        return [
            'name' => $this->translator->translate('voyti.view.name_label', category: 'voyti'),
            'publicEmail' => $this->translator->translate('voyti.view.public_email_label', category: 'voyti'),
            'gravatarEmail' => $this->translator->translate('voyti.view.gravatar_email_label', category: 'voyti'),
            'location' => $this->translator->translate('voyti.view.location_label', category: 'voyti'),
            'website' => $this->translator->translate('voyti.view.website_label', category: 'voyti'),
            'timezone' => $this->translator->translate('voyti.view.timezone_label', category: 'voyti'),
            'bio' => $this->translator->translate('voyti.view.bio_label', category: 'voyti'),
            'birthday' => $this->translator->translate('voyti.view.birthday_label', category: 'voyti'),
        ];
    }

    #[\Override]
    public function getValidationPropertyLabels(): array
    {
        return $this->getPropertyLabels();
    }

    public function validateBirthdayNotInFuture(mixed $value): Result
    {
        $result = new Result();
        $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        if ($birthDate !== false && AgeHelper::calculate($birthDate) === null) {
            $result->addError("'{$value}' must not be in the future.");
        }
        return $result;
    }

    public function validateNoHtmlTags(mixed $value): Result
    {
        $result = new Result();
        $value = (string) $value;
        if (strip_tags($value) !== $value) {
            $result->addError("'{$value}' must not contain HTML tags.");
        }
        return $result;
    }

    public function validateTimezone(mixed $value): Result
    {
        $result = new Result();
        if (!TimezoneHelper::isValid((string) $value)) {
            $result->addError("'{$value}' is not a valid timezone identifier.");
        }
        return $result;
    }
}
