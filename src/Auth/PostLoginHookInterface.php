<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Auth;

use YiiRocks\Voyti\Model\User;

/**
 * A side effect to run against a user whose login has just been completed (e.g. connecting a
 * pending social account created during an OAuth2 signup that was finished with a password login
 * instead). Handlers are collected via the `voyti.post-login-hook` DI tag and consulted in
 * registration order. This is the seam through which extension packages
 * hook into login completion without core referencing them.
 */
interface PostLoginHookInterface
{
    public function handle(User $user): void;
}
