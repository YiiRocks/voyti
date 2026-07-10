<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Entity;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface;

final class User extends ActiveRecord implements IdentityInterface, CookieLoginIdentityInterface
{
    use PrivatePropertiesTrait;
    public const int NEW_EMAIL_CONFIRMED = 0b10;

    public const int OLD_EMAIL_CONFIRMED = 0b01;

    public ?string $password = null;
    private int|bool $anonymized = false;
    private string $auth_key = '';
    private int|bool $auth_tf_enabled = false;
    private ?string $auth_tf_key = null;
    private ?string $auth_tf_type = null;
    private ?int $blocked_at = null;
    private ?int $confirmed_at = null;
    private int $created_at = 0;
    private string $email = '';
    private int $flags = 0;
    private int|bool $gdpr_consent = false;
    private ?int $gdpr_consent_date = null;
    private ?int $id = null;
    private ?int $last_login_at = null;
    private ?string $last_login_ip = null;
    private ?int $password_changed_at = null;
    private string $password_hash = '';
    private ?string $registration_ip = null;
    private ?string $unconfirmed_email = null;
    private int $updated_at = 0;
    private string $username = '';

    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    public function getAuthTfKey(): ?string
    {
        return $this->auth_tf_key;
    }

    public function getAuthTfType(): ?string
    {
        return $this->auth_tf_type;
    }

    public function getBlockedAt(): ?int
    {
        return $this->blocked_at;
    }

    public function getConfirmedAt(): ?int
    {
        return $this->confirmed_at;
    }

    #[\Override]
    public function getCookieLoginKey(): string
    {
        return $this->auth_key;
    }

    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFlags(): int
    {
        return $this->flags;
    }

    public function getGdprConsentDate(): ?int
    {
        return $this->gdpr_consent_date;
    }

    /**
     * @return null|numeric-string
     */
    #[\Override]
    public function getId(): ?string
    {
        return $this->id !== null ? (string) $this->id : null;
    }

    public function getIdOrZero(): int
    {
        return $this->getId() !== null ? (int) $this->getId() : 0;
    }

    public function getLastLoginAt(): ?int
    {
        return $this->last_login_at;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->last_login_ip;
    }

    public function getPasswordAge(): int
    {
        if ($this->password_changed_at === null) {
            return 9999;
        }
        return (int)((time() - $this->password_changed_at) / 86400);
    }

    public function getPasswordChangedAt(): ?int
    {
        return $this->password_changed_at;
    }

    public function getPasswordHash(): string
    {
        return $this->password_hash;
    }

    public function getProfile(): ?UserProfile
    {
        /** @var ?UserProfile */
        return $this->hasOne(UserProfile::class, ['user_id' => 'id'])->one();
    }

    public function getRegistrationIp(): ?string
    {
        return $this->registration_ip;
    }

    public function getSocialNetworkAccounts(): ActiveQueryInterface
    {
        return $this->hasMany(UserSocialAccount::class, ['user_id' => 'id']);
    }

    /**
     * @return (ActiveRecordInterface|array)[]
     *
     * @psalm-return array<ActiveRecordInterface|array>
     */
    public function getTokens(): array
    {
        return $this->hasMany(UserToken::class, ['user_id' => 'id'])->all();
    }

    public function getUnconfirmedEmail(): ?string
    {
        return $this->unconfirmed_email;
    }

    public function getUpdatedAt(): int
    {
        return $this->updated_at;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function isAdminByList(array $administrators): bool
    {
        return in_array($this->username, $administrators, true);
    }

    public function isAnonymized(): bool
    {
        return (bool) $this->anonymized;
    }

    public function isAuthTfEnabled(): bool
    {
        return (bool) $this->auth_tf_enabled;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function isGdprConsent(): bool
    {
        return (bool) $this->gdpr_consent;
    }

    public function setAnonymized(int|bool $anonymized): void
    {
        $this->anonymized = $anonymized;
    }

    public function setAuthKey(string $authKey): void
    {
        $this->auth_key = $authKey;
    }

    public function setAuthTfEnabled(int|bool $authTfEnabled): void
    {
        $this->auth_tf_enabled = $authTfEnabled;
    }

    public function setAuthTfKey(?string $authTfKey): void
    {
        $this->auth_tf_key = $authTfKey;
    }

    public function setAuthTfType(?string $authTfType): void
    {
        $this->auth_tf_type = $authTfType;
    }

    public function setBlockedAt(?int $blockedAt): void
    {
        $this->blocked_at = $blockedAt;
    }

    public function setConfirmedAt(?int $confirmedAt): void
    {
        $this->confirmed_at = $confirmedAt;
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setFlags(int $flags): void
    {
        $this->flags = $flags;
    }

    public function setGdprConsent(int|bool $gdprConsent): void
    {
        $this->gdpr_consent = $gdprConsent;
    }

    public function setGdprConsentDate(?int $gdprConsentDate): void
    {
        $this->gdpr_consent_date = $gdprConsentDate;
    }

    public function setLastLoginAt(?int $lastLoginAt): void
    {
        $this->last_login_at = $lastLoginAt;
    }

    public function setLastLoginIp(?string $lastLoginIp): void
    {
        $this->last_login_ip = $lastLoginIp;
    }

    public function setPasswordChangedAt(?int $passwordChangedAt): void
    {
        $this->password_changed_at = $passwordChangedAt;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->password_hash = $passwordHash;
    }

    public function setRegistrationIp(?string $registrationIp): void
    {
        $this->registration_ip = $registrationIp;
    }

    public function setUnconfirmedEmail(?string $unconfirmedEmail): void
    {
        $this->unconfirmed_email = $unconfirmedEmail;
    }

    public function setUpdatedAt(int $updatedAt): void
    {
        $this->updated_at = $updatedAt;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * @return string
     *
     * @psalm-return '{{%user}}'
     */
    #[\Override]
    public function tableName(): string
    {
        return '{{%user}}';
    }

    public function validateAuthKey(string $authKey): bool
    {
        return $this->auth_key === $authKey;
    }

    #[\Override]
    public function validateCookieLoginKey(string $key): bool
    {
        return $this->validateAuthKey($key);
    }
}
