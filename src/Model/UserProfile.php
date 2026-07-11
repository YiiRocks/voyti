<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

final class UserProfile extends ActiveRecord
{
    use PrivatePropertiesTrait;
    private ?string $bio = null;
    private ?string $gravatar_email = null;
    private ?string $location = null;
    private ?string $name = null;
    private ?string $public_email = null;
    private ?string $timezone = null;
    private ?int $user_id = null;
    private ?string $website = null;

    public static function findByUserId(int $userId): ?UserProfile
    {
        /** @var ?UserProfile $userProfile */
        $userProfile = self::query()->where(['user_id' => $userId])->one();
        return $userProfile;
    }

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
        $email = $this->gravatar_email;
        if ($email === null || $email === '') {
            $email = $this->public_email;
        }
        if ($email === null || $email === '') {
            if ($this->user_id === null || $this->db()->getSchema()->getTableSchema('{{%user}}', true) === null) {
                return null;
            }
            $user = $this->getUser();
            $email = $user?->getEmail();
        }
        if ($email === null || $email === '') {
            return null;
        }
        return hash('sha256', strtolower(trim($email)));
    }

    public function getGravatarUrl(int $size = 256): ?string
    {
        $id = $this->getGravatarId();
        return $id === null ? null : "https://www.gravatar.com/avatar/{$id}?s={$size}&d=mp";
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

    /**
     * @psalm-return list{'user_id'}
     */
    #[\Override]
    public function primaryKey(): array
    {
        return ['user_id'];
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }

    public function setGravatarEmail(?string $gravatarEmail): void
    {
        $this->gravatar_email = $gravatarEmail;
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
