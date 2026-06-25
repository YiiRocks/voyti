<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

abstract class AbstractAuthClient implements AuthClientInterface
{
    public function __construct(
        private readonly string $authUrl,
        private readonly string $name,
        private readonly string $scope,
        private readonly string $title,
        private readonly string $tokenUrl,
    ) {
    }

    #[\Override]
    public function getAuthUrl(): string
    {
        return $this->authUrl;
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function getScope(): string
    {
        return $this->scope;
    }

    #[\Override]
    public function getTitle(): string
    {
        return $this->title;
    }

    #[\Override]
    public function getTokenUrl(): string
    {
        return $this->tokenUrl;
    }
}
