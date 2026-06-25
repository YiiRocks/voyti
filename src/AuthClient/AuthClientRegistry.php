<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

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

    public function get(string $name): ?AuthClientInterface
    {
        return $this->clients[$name] ?? null;
    }
}
