<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Entity;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

final class Profile extends ActiveRecord
{
    use PrivatePropertiesTrait;
    private ?int $userId = null;
    private ?string $name = null;
    private ?string $publicEmail = null;
    private ?string $gravatarEmail = null;
    private ?string $gravatarId = null;
    private ?string $location = null;
    private ?string $website = null;
    private ?string $bio = null;
    private ?string $timezone = null;

    public function tableName(): string
    {
        return '{{%profile}}';
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPublicEmail(): ?string
    {
        return $this->publicEmail;
    }

    public function setPublicEmail(?string $publicEmail): void
    {
        $this->publicEmail = $publicEmail;
    }

    public function getGravatarEmail(): ?string
    {
        return $this->gravatarEmail;
    }

    public function setGravatarEmail(?string $gravatarEmail): void
    {
        $this->gravatarEmail = $gravatarEmail;
    }

    public function getGravatarId(): ?string
    {
        return $this->gravatarId;
    }

    public function setGravatarId(?string $gravatarId): void
    {
        $this->gravatarId = $gravatarId;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): void
    {
        $this->website = $website;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function getUser(): ?User
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function save(?array $properties = null): void
    {
        if ($this->gravatarEmail !== null && $this->gravatarEmail !== '') {
            $this->gravatarId = md5(strtolower(trim($this->gravatarEmail)));
        } elseif ($this->gravatarEmail === null || $this->gravatarEmail === '') {
            $this->gravatarId = null;
        }

        parent::save($properties);
    }
}
