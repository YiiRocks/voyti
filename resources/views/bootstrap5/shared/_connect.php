<?php

declare(strict_types=1);

use YiiRocks\Voyti\AuthClient\AuthClientInterface;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var AuthClientRegistry $authClients
 * @var list<string>|null $excludedProviders
 * @var string|null $routeName
 * @var UrlGeneratorInterface $url
 */

$excludedProviders ??= [];
$routeName ??= 'voyti/session-auth';

$clients = array_filter(
    $authClients->all(),
    static fn (AuthClientInterface $client): bool => !in_array($client->getName(), $excludedProviders, true),
);

if ($clients !== []) {
    echo Html::div()->class('btn-group')->open();
    foreach ($clients as $client) {
        echo Html::a(
            $client->getTitle(),
            $url->generate($routeName, ['provider' => $client->getName()]),
        )->class('btn btn-primary');
    }
    echo Html::div()->close();
}
