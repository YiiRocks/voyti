<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var array $rules Array of rule class names (string[])
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.rule.title', category: 'voyti'));

echo Html::div()->class('voyti-rbac-index')->open();
    echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
        echo Html::H1($translator->translate('voyti.view.rule.title', category: 'voyti'));
        echo Html::a($translator->translate('voyti.view.rule.create_link', category: 'voyti'), $url->generate('voyti/rules-create'))->class('btn', 'btn-primary');
    echo Html::div()->close();

    echo Html::div()->class('table-responsive')->open();
        echo Html::table()->class('table table-striped table-hover')->open();

        echo Html::tag('thead')->class('table-light')->open();
            echo Html::tag('tr')->open();
                echo Html::tag('th', $translator->translate('voyti.view.name_header', category: 'voyti'))->addAttributes(['scope' => 'col']);
                echo Html::tag('th', $translator->translate('voyti.view.actions_header', category: 'voyti'))->class('text-end')->addAttributes(['scope' => 'col']);
            echo Html::tag('tr')->close();
        echo Html::tag('thead')->close();

        echo Html::tag('tbody')->open();

        foreach ($rules as $ruleName) {
            echo Html::tag('tr')->open();
                echo Html::tag('td', Html::encode($ruleName));
                echo Html::tag('td')->class('text-end')->open();
                    echo Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/rules-update', ['name' => $ruleName]))->class('btn', 'btn-sm', 'btn-outline-secondary');
                    echo ' ';

                    echo Html::form()
                        ->post($url->generate('voyti/rules-delete', ['name' => $ruleName]))
                        ->csrf($csrf)
                        ->class('d-inline')
                        ->open();

                    echo Field::buttonGroup()
                        ->buttons(
                            Html::submitButton($translator->translate('voyti.view.delete_button', category: 'voyti'))->class('btn', 'btn-sm', 'btn-outline-danger')
                        );

                    echo Html::form()->close();
                echo Html::tag('td')->close();
            echo Html::tag('tr')->close();
        }

        echo Html::tag('tbody')->close();
        echo Html::table()->close();
    echo Html::div()->close();
echo Html::div()->close();
