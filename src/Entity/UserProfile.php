<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Entity;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

final class UserProfile extends ActiveRecord
{
    use PrivatePropertiesTrait;
    private ?string $bio = null;
    private ?string $gravatar_email = null;
    private ?string $gravatar_id = null;
    private ?string $location = null;
    private ?string $name = null;
    private ?string $public_email = null;
    private ?string $timezone = null;
    private ?int $user_id = null;
    private ?string $website = null;

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function getGravatarEmail(): ?string
    {
        return $this->gravatar_email;
    }

    public function getGravatarId(): ?string
    {
        return $this->gravatar_id;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPublicEmail(): ?string
    {
        return $this->public_email;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function getUser(): ?User
    {
        /** @var ?User */
        return $this->hasOne(User::class, ['id' => 'user_id'])->one();
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    #[\Override]
    public function save(?array $properties = null): void
    {
        if ($this->gravatar_email !== null && $this->gravatar_email !== '') {
            $this->gravatar_id = md5(strtolower(trim($this->gravatar_email)));
        } else {
            $this->gravatar_id = null;
        }

        parent::save($properties);
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }

    public function setGravatarEmail(?string $gravatarEmail): void
    {
        $this->gravatar_email = $gravatarEmail;
    }

    public function setGravatarId(?string $gravatarId): void
    {
        $this->gravatar_id = $gravatarId;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function setPublicEmail(?string $publicEmail): void
    {
        $this->public_email = $publicEmail;
    }

    public function setTimezone(?string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function setUserId(int $userId): void
    {
        $this->user_id = $userId;
    }

    public function setWebsite(?string $website): void
    {
        $this->website = $website;
    }

    /**
     * @return string
     *
     * @psalm-return '{{%user_profile}}'
     */
    #[\Override]
    public function tableName(): string
    {
        return '{{%user_profile}}';
    }
}
