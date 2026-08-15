<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Auth;

use YiiRocks\Voyti\Model\User;

/**
 * A side effect to run against a newly registered user once registration has succeeded (e.g.
 * connecting a pending social account created during the OAuth2 signup flow). Handlers are
 * collected via the `voyti.post-registration-hook` DI tag and consulted in registration order. This
 * is the seam through which packages such as `yiirocks/voyti-social-auth` hook into the registration
 * flow without core referencing them.
 */
interface PostRegistrationHookInterface
{
    public function handle(User $user): void;
}
