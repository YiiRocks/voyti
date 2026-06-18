<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event;

use YiiRocks\Voyti\Entity\User;

final class MailEvent
{
    public const TYPE_WELCOME = 'welcome';
    public const TYPE_CONFIRM = 'confirm';
    public const TYPE_RECOVERY = 'recovery';
    public const TYPE_RECONFIRM = 'reconfirm';
    public const TYPE_TWOFACTORCODE = 'twofactorcode';

    public function __construct(
        private readonly string $type,
        private readonly ?User $user = null,
        private ?string $email = null,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }
}
