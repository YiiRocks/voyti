<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Model\User;

/**
 * A step that may interrupt a successful password login to demand an additional check before the
 * session is established (e.g. two-factor authentication). Handlers are collected via the
 * `voyti.login-challenge` DI tag and consulted in order; the first one to return a response
 * short-circuits login with that response (typically a challenge screen), while returning null lets
 * login proceed. This is the seam through which extension packages hook into the login flow without
 * the core referencing them.
 */
interface LoginChallengeInterface
{
    public function challenge(User $user, bool $rememberMe, ServerRequestInterface $request): ?ResponseInterface;
}
