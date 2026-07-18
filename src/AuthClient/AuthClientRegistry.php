<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

/**
 * Lookup collection of configured {@see AuthClientInterface} instances, keyed by provider name.
 */
final class AuthClientRegistry
{
    /**
     * @var array<string, AuthClientInterface>
     */
    private array $clients = [];

    public function __construct(AuthClientInterface ...$clients)
    {
        foreach ($clients as $client) {
            $this->clients[$client->getName()] = $client;
        }
    }

    /**
     * @return list<AuthClientInterface>
     */
    public function all(): array
    {
        return array_values($this->clients);
    }

    /**
     * @param list<string> $excluded
     *
     * @return list<AuthClientInterface>
     */
    public function allExcept(array $excluded): array
    {
        return array_values(array_filter(
            $this->clients,
            static fn(AuthClientInterface $client): bool => !in_array($client->getName(), $excluded, true),
        ));
    }

    public function get(string $name): ?AuthClientInterface
    {
        return $this->clients[$name] ?? null;
    }

    public function getTitle(string $name): string
    {
        return $this->get($name)?->getTitle() ?? $name;
    }
}
