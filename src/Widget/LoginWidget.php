<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Widget;

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use YiiRocks\Voyti\Form\LoginForm;

final class LoginWidget
{
    public function __construct(
        private readonly UrlGeneratorInterface $url,
    ) {
    }

    public function render(LoginForm $form): string
    {
        $action = $this->url->generate('voyti/login');
        $html = '<form action="' . Html::encode($action) . '" method="post">';
        $html .= '<input type="text" name="login[login]" placeholder="Username or Email" />';
        $html .= '<input type="password" name="login[password]" placeholder="Password" />';
        $html .= '<label><input type="checkbox" name="login[rememberMe]" value="1" /> Remember me</label>';
        $html .= '<button type="submit">Sign in</button>';
        $html .= '</form>';
        return $html;
    }
}
