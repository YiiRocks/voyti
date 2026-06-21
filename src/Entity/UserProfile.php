<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Entity;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

final class UserProfile extends ActiveRecord
{
    use PrivatePropertiesTrait;
    private ?string $bio = null;
    private ?string $gravatarEmail = null;
    private ?string $gravatarId = null;
    private ?string $location = null;
    private ?string $name = null;
    private ?string $publicEmail = null;
    private ?string $timezone = null;
    private ?int $userId = null;
    private ?string $website = null;

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function getGravatarEmail(): ?string
    {
        return $this->gravatarEmail;
    }

    public function getGravatarId(): ?string
    {
        return $this->gravatarId;
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
        return $this->publicEmail;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function getUser(): ?User
    {
        /** @var ?User */
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    #[\Override]
    public function save(?array $properties = null): void
    {
        if ($this->gravatarEmail !== null && $this->gravatarEmail !== '') {
            $this->gravatarId = md5(strtolower(trim($this->gravatarEmail)));
        } elseif ($this->gravatarEmail === null || $this->gravatarEmail === '') {
            $this->gravatarId = null;
        }

        parent::save($properties);
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }

    public function setGravatarEmail(?string $gravatarEmail): void
    {
        $this->gravatarEmail = $gravatarEmail;
    }

    public function setGravatarId(?string $gravatarId): void
    {
        $this->gravatarId = $gravatarId;
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
        $this->publicEmail = $publicEmail;
    }

    public function setTimezone(?string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function setWebsite(?string $website): void
    {
        $this->website = $website;
    }

    #[\Override]
    public function tableName(): string
    {
        return '{{%user_profile}}';
    }
}
