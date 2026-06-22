<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Entity;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

final class UserSocialAccount extends ActiveRecord
{
    use PrivatePropertiesTrait;
    private string $client_id = '';
    private ?string $code = null;
    private int $created_at = 0;
    private ?string $data = null;

    private ?array $decodedData = null;
    private ?string $email = null;
    private ?int $id = null;
    private string $provider = '';
    private ?int $user_id = null;
    private ?string $username = null;

    public function connect(User $user): bool
    {
        $this->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $this->setUsername(null);
        $this->setEmail(null);
        $this->setCode(null);
        $this->save();
        return true;
    }

    public function getClientId(): string
    {
        return $this->client_id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function getDecodedData(): ?array
    {
        if ($this->data !== null && $this->decodedData === null) {
            /** @var ?array $this->decodedData */
            $this->decodedData = json_decode($this->data, true);
        }
        return $this->decodedData;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getUser(): ?User
    {
        /** @var ?User */
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function isConnected(): bool
    {
        return $this->user_id !== null;
    }

    public function setClientId(string $clientId): void
    {
        $this->client_id = $clientId;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function setData(?string $data): void
    {
        $this->data = $data;
        $this->decodedData = null;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    public function setUserId(?int $userId): void
    {
        $this->user_id = $userId;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    #[\Override]
    public function tableName(): string
    {
        return '{{%user_social_account}}';
    }
}
