<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Settings\TwoFactorCodeForm;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use YiiRocks\Voyti\ViewData\TwoFactor\IndexViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Json\Json;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var TwoFactorCodeForm $form
 * @var IndexViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.two_factor.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash]);

echo Html::H1($translator->translate('voyti.view.two_factor.title'));

if (!empty($data->errors)) {
    echo Html::div()->class('alert alert-danger')->open();
    foreach ($data->errors as $fieldErrors) {
        foreach ($fieldErrors as $error) {
            echo Html::div($error);
        }
    }
    echo Html::div()->close();
}

if ($data->isEnabled) {
    echo Html::p($data->enabledWithMethodMessage);

    if ($data->method === 'email' && !$data->emailCodeSent) {
        echo Html::div()->class('alert alert-info')->open();
        echo $translator->translate('voyti.view.two_factor.disable_confirm_intro');
        echo Html::div()->close();

        echo Html::form()
            ->post($data->disableSendCodeUrl)
            ->csrf($csrf)
            ->open();

        echo Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.two_factor.disable_send_code'))->class('btn', 'btn-danger')->attribute('tabindex', 1),
            );

        echo Html::form()->close();
    } else {
        if ($data->method === 'email') {
            echo Html::div()->class('alert alert-info')->open();
            echo $translator->translate('voyti.view.two_factor_email.enter_code');
            echo Html::div()->close();
        }

        echo Html::form()
            ->post($data->disableUrl)
            ->csrf($csrf)
            ->open();

        echo Html::p($translator->translate('voyti.view.two_factor.backup_code_hint'))->class('text-muted small');

        echo Field::text($form, 'code')->tabIndex(1);

        echo Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.two_factor.disable'))->class('btn', 'btn-danger')->attribute('tabindex', 2),
            );

        echo Html::form()->close();

        echo Html::hr();
        echo Html::H2($translator->translate('voyti.view.two_factor.regenerate_backup_codes'))->class('h5');
        echo Html::p($translator->translate('voyti.view.two_factor.regenerate_backup_codes_intro'))->class('text-muted small');

        if (!$data->hasBackupCodes) {
            echo Html::div($translator->translate('voyti.view.two_factor.no_backup_codes_remaining'))->class('alert alert-warning');
        }

        echo Html::form()
            ->post($data->regenerateBackupCodesUrl)
            ->csrf($csrf)
            ->open();

        echo Field::text($form, 'code')->tabIndex(3);

        echo Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.two_factor.regenerate_backup_codes'))->class('btn', 'btn-secondary')->attribute('tabindex', 4),
            );

        echo Html::form()->close();
    }
} else {
    echo Html::div()->class('d-flex justify-content-center mb-3')->open();
    echo Html::div()->class('btn-group')->open();
    echo Html::a($translator->translate('voyti.view.two_factor_google.button_label'), $data->googleUrl)
        ->class('btn', $data->method === 'google' ? 'btn-primary' : 'btn-outline-primary')
        ->attribute('data-voyti-2fa-method', 'google');
    echo Html::a($translator->translate('voyti.view.two_factor_email.button_label'), $data->emailUrl)
        ->class('btn', $data->method === 'email' ? 'btn-primary' : 'btn-outline-primary')
        ->attribute('data-voyti-2fa-method', 'email');
    echo Html::div()->close();
    echo Html::div()->close();

    echo Html::div()->id('voyti-2fa-content')->open();
    if (!$data->preloadContent) {
        echo Html::div()->class('d-flex justify-content-center')->open();
        echo Html::div()
            ->class('spinner-border')
            ->attribute('role', 'status')
            ->content(Html::span($translator->translate('voyti.view.two_factor.loading'))->class('visually-hidden'));
        echo Html::div()->close();
    } elseif ($data->method === 'email') {
        /** @psalm-suppress InvalidScope */
        echo $this->render('./_email', [
            'data' => $data->emailSetup,
            'form' => $form,
            'translator' => $translator,
            'csrf' => $csrf,
        ]);
    } else {
        /** @psalm-suppress InvalidScope */
        echo $this->render('./_google', [
            'data' => $data->googleSetup,
            'form' => $form,
            'translator' => $translator,
            'csrf' => $csrf,
        ]);
    }
    echo Html::div()->close();

    $switchConfig = [
        'renewUrl' => $data->renewUrl,
        // Json::encode() only reads public properties via get_object_vars(), so passing
        // the Csrf object itself would silently serialize as {} - force the string value.
        'csrfToken' => $csrf . '',
        'renewErrorMessage' => $data->renewErrorMessage,
        'autoloadUrl' => $data->autoloadUrl,
        'autoloadMethod' => $data->method,
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
