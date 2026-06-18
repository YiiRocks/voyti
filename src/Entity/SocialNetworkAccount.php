<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Entity;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

final class SocialNetworkAccount extends ActiveRecord
{
    use PrivatePropertiesTrait;
    private ?int $id = null;
    private ?int $userId = null;
    private string $provider = '';
    private string $clientId = '';
    private ?string $data = null;
    private ?string $code = null;
    private ?string $email = null;
    private ?string $username = null;
    private int $createdAt = 0;

    private ?array $decodedData = null;

    public function tableName(): string
    {
        return '{{%social_account}}';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(?string $data): void
    {
        $this->data = $data;
        $this->decodedData = null;
    }

    public function getDecodedData(): ?array
    {
        if ($this->data !== null && $this->decodedData === null) {
            $this->decodedData = json_decode($this->data, true);
        }
        return $this->decodedData;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function isConnected(): bool
    {
        return $this->userId !== null;
    }

    public function getUser(): ?User
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function connect(User $user): bool
    {
        $this->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $this->setUsername(null);
        $this->setEmail(null);
        $this->setCode(null);
        return $this->save();
    }
}
