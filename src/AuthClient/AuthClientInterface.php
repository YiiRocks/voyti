<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

interface AuthClientInterface
{
    public function getAuthUrl(): string;

    public function getName(): string;

    public function getScope(): string;

    public function getTitle(): string;

    public function getTokenUrl(): string;
}
