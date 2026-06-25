<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Widget;

use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

final class ConnectWidget
{
    public function __construct(
        private readonly UrlGeneratorInterface $url,
        private readonly AuthClientRegistry $authClients,
    ) {
    }

    public function render(): string
    {
        $html = '<div class="social-auth">';
        foreach ($this->authClients->all() as $client) {
            $authUrl = $this->url->generate('voyti/auth', ['provider' => $client->getName()]);
            $html .= '<a href="' . Html::encode($authUrl) . '" class="btn btn-social">' . Html::encode($client->getTitle()) . '</a> ';
        }
        $html .= '</div>';

        return $html;
    }
}
