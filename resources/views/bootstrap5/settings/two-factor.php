<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Form\Settings\TwoFactorCodeForm;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Json\Json;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var User $user
 * @var string $method
 * @var string $qrCodeUri
 * @var string|null $secret
 * @var bool $emailCodeSent
 * @var bool $preloadContent
 * @var ModuleConfig $config
 * @var array<string, list<string>> $errors
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.two_factor.title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['config' => $config, 'url' => $url, 'translator' => $translator]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash]);

echo Html::H1($translator->translate('voyti.view.two_factor.title', category: 'voyti'));

if (!empty($errors)) {
    echo Html::div()->class('alert alert-danger')->open();
    foreach ($errors as $field => $fieldErrors) {
        /** @var string $error */
        foreach ($fieldErrors as $error) {
            echo Html::div($error);
        }
    }
    echo Html::div()->close();
}

if ($user->isAuthTfEnabled()) {
    $methodName = $translator->translate(
        $method === 'email' ? 'voyti.view.two_factor_email.method_name' : 'voyti.view.two_factor_google.button_label',
        category: 'voyti',
    );
    echo Html::p($translator->translate('voyti.view.two_factor.enabled_with_method', ['method' => $methodName], category: 'voyti'));

    if ($method === 'email' && !$emailCodeSent) {
        echo Html::div()->class('alert alert-info')->open();
        echo $translator->translate('voyti.view.two_factor.disable_confirm_intro', category: 'voyti');
        echo Html::div()->close();

        echo Html::form()
            ->post($url->generate('voyti/settings-two-factor-disable-send-code'))
            ->csrf($csrf)
            ->open();

        echo Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.two_factor.disable_send_code', category: 'voyti'))->class('btn', 'btn-danger')->attribute('tabindex', 1),
            );

        echo Html::form()->close();
    } else {
        if ($method === 'email') {
            echo Html::div()->class('alert alert-info')->open();
            echo $translator->translate('voyti.view.two_factor_email.enter_code', category: 'voyti');
            echo Html::div()->close();
        }

        echo Html::form()
            ->post($url->generate('voyti/settings-two-factor-disable'))
            ->csrf($csrf)
            ->open();

        echo Field::text(new TwoFactorCodeForm($translator, $method), 'code')->addInputAttributes(['inputmode' => 'numeric'])->tabIndex(1);

        echo Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.two_factor.disable', category: 'voyti'))->class('btn', 'btn-danger')->attribute('tabindex', 2)
            );

        echo Html::form()->close();
    }
} else {
    $googleUrl = $url->generate('voyti/settings-two-factor-google');
    $emailUrl = $url->generate('voyti/settings-two-factor-email');

    echo Html::div()->class('d-flex justify-content-center mb-3')->open();
    echo Html::div()->class('btn-group')->open();
    echo Html::a($translator->translate('voyti.view.two_factor_google.button_label', category: 'voyti'), $googleUrl)
        ->class('btn', $method === 'google' ? 'btn-primary' : 'btn-outline-primary')
        ->attribute('data-voyti-2fa-method', 'google');
    echo Html::a($translator->translate('voyti.view.two_factor_email.button_label', category: 'voyti'), $emailUrl)
        ->class('btn', $method === 'email' ? 'btn-primary' : 'btn-outline-primary')
        ->attribute('data-voyti-2fa-method', 'email');
    echo Html::div()->close();
    echo Html::div()->close();

    echo Html::div()->id('voyti-2fa-content')->open();
    if (!$preloadContent) {
        echo Html::div()->class('d-flex justify-content-center')->open();
        echo Html::div()
            ->class('spinner-border')
            ->attribute('role', 'status')
            ->content(Html::span($translator->translate('voyti.view.two_factor.loading', category: 'voyti'))->class('visually-hidden'));
        echo Html::div()->close();
    } elseif ($method === 'email') {
        /** @psalm-suppress InvalidScope */
        echo $this->render('./two-factor/_email', [
            'user' => $user,
            'emailCodeSent' => $emailCodeSent,
            'config' => $config,
            'url' => $url,
            'translator' => $translator,
            'csrf' => $csrf,
        ]);
    } else {
        /** @psalm-suppress InvalidScope */
        echo $this->render('./two-factor/_google', [
            'user' => $user,
            'qrCodeUri' => $qrCodeUri,
            'secret' => $secret,
            'config' => $config,
            'url' => $url,
            'translator' => $translator,
            'csrf' => $csrf,
        ]);
    }
    echo Html::div()->close();

    $switchConfig = [
        'renewUrl' => $url->generate('voyti/settings-two-factor-google-renew'),
        // Json::encode() only reads public properties via get_object_vars(), so passing
        // the Csrf object itself would silently serialize as {} - force the string value.
        'csrfToken' => $csrf . '',
        'renewErrorMessage' => $translator->translate('voyti.view.two_factor.renew_error', category: 'voyti'),
        'autoloadUrl' => $preloadContent ? null : ($method === 'email' ? $emailUrl : $googleUrl),
        'autoloadMethod' => $method,
    ];
    echo Html::script(
        '(function(){'
        . 'var cfg=' . Json::htmlEncode($switchConfig) . ';'
        . 'var content=document.getElementById("voyti-2fa-content");'
        . 'var buttons=document.querySelectorAll("[data-voyti-2fa-method]");'
        . 'function setActive(method){'
        . 'buttons.forEach(function(b){'
        . 'var active=b.getAttribute("data-voyti-2fa-method")===method;'
        . 'b.classList.toggle("btn-primary",active);'
        . 'b.classList.toggle("btn-outline-primary",!active);'
        . '});'
        . '}'
        . 'function loadMethod(method,fragmentUrl){'
        . 'if(!content||!fragmentUrl){return;}'
        . 'fetch(fragmentUrl,{headers:{"Accept":"text/html","X-Requested-With":"XMLHttpRequest"},credentials:"same-origin"})'
        . '.then(function(response){if(!response.ok){throw new Error("load failed");}return response.text();})'
        . '.then(function(html){'
        . 'content.innerHTML=html;'
        . 'setActive(method);'
        . 'window.history.replaceState(null,"",fragmentUrl);'
        . '})'
        . '.catch(function(){window.location.href=fragmentUrl;});'
        . '}'
        . 'buttons.forEach(function(btn){'
        . 'btn.addEventListener("click",function(e){'
        . 'if(e.defaultPrevented||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey){return;}'
        . 'e.preventDefault();'
        . 'loadMethod(btn.getAttribute("data-voyti-2fa-method"),btn.getAttribute("href"));'
        . '});'
        . '});'
        . 'if(content){'
        . 'content.addEventListener("click",function(e){'
        . 'var btn=e.target.closest("#voyti-2fa-renew");'
        . 'if(!btn){return;}'
        . 'btn.disabled=true;'
        . 'var body=new URLSearchParams();'
        . 'body.set("_csrf",cfg.csrfToken);'
        . 'fetch(cfg.renewUrl,{method:"POST",headers:{"Accept":"application/json"},credentials:"same-origin",body:body})'
        . '.then(function(response){if(!response.ok){throw new Error("renew failed");}return response.json();})'
        . '.then(function(data){'
        . 'var qrEl=document.getElementById("voyti-2fa-qr");'
        . 'if(qrEl&&data.qrCodeUri){qrEl.innerHTML=data.qrCodeUri;}'
        . 'var secretEl=document.getElementById("voyti-2fa-secret");'
        . 'if(secretEl&&data.secret){secretEl.textContent=data.secret;}'
        . 'btn.disabled=false;'
        . '})'
        . '.catch(function(){window.alert(cfg.renewErrorMessage);btn.disabled=false;});'
        . '});'
        . '}'
        . 'if(cfg.autoloadUrl){loadMethod(cfg.autoloadMethod,cfg.autoloadUrl);}'
        . '})();',
    )->render();
}
echo Html::div()->close();
