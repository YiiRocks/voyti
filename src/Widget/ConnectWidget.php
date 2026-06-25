<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Widget;

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

final class ConnectWidget
{
    public function __construct(
        private readonly UrlGeneratorInterface $url,
    ) {
    }

    public function render(array $clients = []): string
    {
        $html = '<div class="social-auth">';
        foreach ($clients as $client) {
            if (!is_string($client) || $client === '') {
                continue;
            }
            $authUrl = $this->url->generate('voyti/auth', ['authclient' => $client]);
            $html .= '<a href="' . Html::encode($authUrl) . '" class="btn btn-social">' . Html::encode($client) . '</a> ';
        }
        $html .= '</div>';
        return $html;
    }
}
