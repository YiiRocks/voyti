<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

/**
 * OAuth client for providers with no behavior beyond the standard {@see AbstractAuthClient} flow —
 * used directly for Google, LinkedIn, Microsoft 365, and templated Keycloak instances.
 */
final readonly class GenericAuthClient extends AbstractAuthClient {}
