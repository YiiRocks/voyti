<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\User;

use YiiRocks\Voyti\Model\User;

final readonly class UserEvent
{
    public const string BLOCK = 'block';
    public const string CONFIRM = 'confirm';
    public const string CREATE = 'create';
    public const string DELETE = 'delete';
    public const string PASSWORD_RESET = 'passwordReset';
    public const string RESTORE_IDENTITY = 'restoreIdentity';
    public const string SWITCH_IDENTITY = 'switchIdentity';
    public const string UNBLOCK = 'unblock';

    public function __construct(
        private User $user,
        private string $type,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
