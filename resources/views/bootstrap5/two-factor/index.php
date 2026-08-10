<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\LinkButtonHelper;
use YiiRocks\Voyti\Model\Form\Settings\TwoFactorCodeForm;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use YiiRocks\Voyti\ViewData\TwoFactor\IndexViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Json\Json;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var TwoFactorCodeForm $form
 * @var IndexViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 * @var Csrf $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.two_factor.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash, 'toast' => $toast ?? null]);

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
            ->buttonsData([
                [$translator->translate('voyti.view.two_factor.disable_send_code'), 'type' => 'submit', 'class' => 'btn btn-danger', 'tabindex' => 1],
            ]);

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
            ->buttonsData([
                [$translator->translate('voyti.view.two_factor.disable'), 'type' => 'submit', 'class' => 'btn btn-danger', 'tabindex' => 2],
            ]);

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
            ->buttonsData([
                [$translator->translate('voyti.view.two_factor.regenerate_backup_codes'), 'type' => 'submit', 'class' => LinkButtonHelper::submitButtonClass(), 'tabindex' => 4],
            ]);

        echo Html::form()->close();
    }
} else {
    echo Html::div()->class('d-flex justify-content-center mb-3')->open();
    echo Html::div()->class('btn-group')->open();
    if ($data->googleUrl !== null) {
        echo Html::a($translator->translate('voyti.view.two_factor_google.button_label'), $data->googleUrl)
            ->class($data->method === 'google' ? LinkButtonHelper::submitButtonClass() : LinkButtonHelper::resetButtonClass())
            ->attribute('data-voyti-2fa-method', 'google');
    }
    echo Html::a($translator->translate('voyti.view.two_factor_email.button_label'), $data->emailUrl)
        ->class($data->method === 'email' ? LinkButtonHelper::submitButtonClass() : LinkButtonHelper::resetButtonClass())
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
        'csrfToken' => (string) $csrf,
        'renewErrorMessage' => $data->renewErrorMessage,
        'autoloadUrl' => $data->autoloadUrl,
        'autoloadMethod' => $data->method,
        'activeClass' => LinkButtonHelper::submitButtonClass(),
        'inactiveClass' => LinkButtonHelper::resetButtonClass(),
    ];
    $switchConfigJson = Json::htmlEncode($switchConfig);

    $js = <<<JS
        (() => {
            const cfg = {$switchConfigJson};

            const content = document.getElementById('voyti-2fa-content');
            const buttons = document.querySelectorAll('[data-voyti-2fa-method]');

            const classNames = value => (value ? value.split(/\\s+/).filter(Boolean) : []);

            const setActive = method => {
                buttons.forEach(button => {
                    const active = button.getAttribute('data-voyti-2fa-method') === method;
                    const onClasses = classNames(active ? cfg.activeClass : cfg.inactiveClass);
                    const offClasses = classNames(active ? cfg.inactiveClass : cfg.activeClass);

                    offClasses.forEach(className => {
                        if (!onClasses.includes(className)) {
                            button.classList.remove(className);
                        }
                    });
                    onClasses.forEach(className => button.classList.add(className));
                });
            };

            const loadMethod = async (method, url) => {
                if (!content || !url) {
                    return;
                }

                try {
                    const response = await fetch(url, {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'text/html',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error();
                    }

                    content.innerHTML = await response.text();
                    setActive(method);
                    history.replaceState(null, '', url);
                } catch {
                    location.href = url;
                }
            };

            buttons.forEach(button => {
                button.addEventListener('click', event => {
                    if (
                        event.defaultPrevented ||
                        event.button !== 0 ||
                        event.metaKey ||
                        event.ctrlKey ||
                        event.shiftKey ||
                        event.altKey
                    ) {
                        return;
                    }

                    event.preventDefault();
                    loadMethod(button.getAttribute('data-voyti-2fa-method'), button.href);
                });
            });

            if (content) {
                content.addEventListener('click', async event => {
                    const button = event.target.closest('#voyti-2fa-renew');

                    if (!button) {
                        return;
                    }

                    button.disabled = true;

                    try {
                        const response = await fetch(cfg.renewUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'application/json',
                            },
                            body: new URLSearchParams({
                                _csrf: cfg.csrfToken,
                            }),
                        });

                        if (!response.ok) {
                            throw new Error();
                        }

                        const data = await response.json();

                        if (data.qrCodeUri) {
                            const qr = document.getElementById('voyti-2fa-qr');
                            if (qr) {
                                qr.innerHTML = data.qrCodeUri;
                            }
                        }

                        if (data.secret) {
                            const secret = document.getElementById('voyti-2fa-secret');
                            if (secret) {
                                secret.textContent = data.secret;
                            }
                        }
                    } catch {
                        alert(cfg.renewErrorMessage);
                    } finally {
                        button.disabled = false;
                    }
                });
            }

            if (cfg.autoloadUrl) {
                loadMethod(cfg.autoloadMethod, cfg.autoloadUrl);
            }
        })();
        JS;

    echo Html::script($js)->render();
}
echo Html::div()->close();
