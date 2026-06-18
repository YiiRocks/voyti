<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Entity;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;
use Yiisoft\Auth\IdentityInterface;

final class User extends ActiveRecord implements IdentityInterface
{
    use PrivatePropertiesTrait;

    public const OLD_EMAIL_CONFIRMED = 0b01;
    public const NEW_EMAIL_CONFIRMED = 0b10;

    public ?string $password = null;
    private ?int $id = null;
    private string $username = '';
    private string $email = '';
    private ?string $unconfirmedEmail = null;
    private string $passwordHash = '';
    private string $authKey = '';
    private ?string $authTfKey = null;
    private int|bool $authTfEnabled = false;
    private ?string $authTfType = null;
    private ?string $authTfMobilePhone = null;
    private ?string $registrationIp = null;
    private ?int $confirmedAt = null;
    private ?int $blockedAt = null;
    private int $flags = 0;
    private int $createdAt = 0;
    private int $updatedAt = 0;
    private ?int $lastLoginAt = null;
    private ?int $gdprConsentDate = null;
    private int|bool $gdprDeleted = false;
    private int|bool $gdprConsent = false;
    private ?string $lastLoginIp = null;
    private ?int $passwordChangedAt = null;

    public function tableName(): string
    {
        return '{{%user}}';
    }

    public function getId(): ?string
    {
        return $this->id !== null ? (string) $this->id : null;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getUnconfirmedEmail(): ?string
    {
        return $this->unconfirmedEmail;
    }

    public function setUnconfirmedEmail(?string $unconfirmedEmail): void
    {
        $this->unconfirmedEmail = $unconfirmedEmail;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getAuthKey(): string
    {
        return $this->authKey;
    }

    public function setAuthKey(string $authKey): void
    {
        $this->authKey = $authKey;
    }

    public function getAuthTfKey(): ?string
    {
        return $this->authTfKey;
    }

    public function setAuthTfKey(?string $authTfKey): void
    {
        $this->authTfKey = $authTfKey;
    }

    public function isAuthTfEnabled(): bool
    {
        return (bool) $this->authTfEnabled;
    }

    public function setAuthTfEnabled(int|bool $authTfEnabled): void
    {
        $this->authTfEnabled = $authTfEnabled;
    }

    public function getAuthTfType(): ?string
    {
        return $this->authTfType;
    }

    public function setAuthTfType(?string $authTfType): void
    {
        $this->authTfType = $authTfType;
    }

    public function getAuthTfMobilePhone(): ?string
    {
        return $this->authTfMobilePhone;
    }

    public function setAuthTfMobilePhone(?string $authTfMobilePhone): void
    {
        $this->authTfMobilePhone = $authTfMobilePhone;
    }

    public function getRegistrationIp(): ?string
    {
        return $this->registrationIp;
    }

    public function setRegistrationIp(?string $registrationIp): void
    {
        $this->registrationIp = $registrationIp;
    }

    public function getConfirmedAt(): ?int
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?int $confirmedAt): void
    {
        $this->confirmedAt = $confirmedAt;
    }

    public function getBlockedAt(): ?int
    {
        return $this->blockedAt;
    }

    public function setBlockedAt(?int $blockedAt): void
    {
        $this->blockedAt = $blockedAt;
    }

    public function isBlocked(): bool
    {
        return $this->blockedAt !== null;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmedAt !== null;
    }

    public function getFlags(): int
    {
        return $this->flags;
    }

    public function setFlags(int $flags): void
    {
        $this->flags = $flags;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(int $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getLastLoginAt(): ?int
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?int $lastLoginAt): void
    {
        $this->lastLoginAt = $lastLoginAt;
    }

    public function isGdprDeleted(): bool
    {
        return (bool) $this->gdprDeleted;
    }

    public function setGdprDeleted(int|bool $gdprDeleted): void
    {
        $this->gdprDeleted = $gdprDeleted;
    }

    public function isGdprConsent(): bool
    {
        return (bool) $this->gdprConsent;
    }

    public function setGdprConsent(int|bool $gdprConsent): void
    {
        $this->gdprConsent = $gdprConsent;
    }

    public function getGdprConsentDate(): ?int
    {
        return $this->gdprConsentDate;
    }

    public function setGdprConsentDate(?int $gdprConsentDate): void
    {
        $this->gdprConsentDate = $gdprConsentDate;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function setLastLoginIp(?string $lastLoginIp): void
    {
        $this->lastLoginIp = $lastLoginIp;
    }

    public function getPasswordChangedAt(): ?int
    {
        return $this->passwordChangedAt;
    }

    public function setPasswordChangedAt(?int $passwordChangedAt): void
    {
        $this->passwordChangedAt = $passwordChangedAt;
    }

    public function getPasswordAge(): int
    {
        if ($this->passwordChangedAt === null) {
            return 9999;
        }
        return (int)((time() - $this->passwordChangedAt) / 86400);
    }

    public function getProfile(): ?Profile
    {
        return $this->hasOne(Profile::class, ['user_id' => 'id']);
    }

    public function getSocialNetworkAccounts(): array
    {
        return $this->hasMany(SocialNetworkAccount::class, ['user_id' => 'id']);
    }

    public function fields(): array
    {
        $fields = parent::fields();
        unset($fields['authKey'], $fields['passwordHash']);
        return $fields;
    }

    public function validateAuthKey(string $authKey): bool
    {
        return $this->authKey === $authKey;
    }

    public function isAdminByList(array $administrators): bool
    {
        return in_array($this->username, $administrators, true);
    }

    public function getTokens(): array
    {
        return $this->hasMany(Token::class, ['user_id' => 'id'])->all();
    }
}
