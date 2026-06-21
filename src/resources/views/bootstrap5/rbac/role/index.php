<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var array $items Array of Role objects
 * @var string $filterName
 * @var string $filterDescription
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.role.title', category: 'voyti'));

echo Html::div()->class('voyti-rbac-index')->open();
    echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
        Html::H1($translator->translate('voyti.view.role.title', category: 'voyti'));
        echo Html::a($translator->translate('voyti.view.role.create_link', category: 'voyti'), $url->generate('voyti/roles-create'))->class('btn', 'btn-primary');
    echo Html::div()->close();

    echo Html::form()
        ->action($url->generate('voyti/roles-index'))
        ->method('get')
        ->class('mb-3')
        ->open();

    echo Html::div()->class('row g-2')->open();
        echo Html::div()->class('col')->open();
            echo Html::input('text')->class('form-control')->name('name')->value(Html::encode($filterName))->placeholder($translator->translate('voyti.view.name_label', category: 'voyti'));
        echo Html::div()->close();

        echo Html::div()->class('col')->open();
            echo Html::input('text')->class('form-control')->name('description')->value(Html::encode($filterDescription))->placeholder($translator->translate('voyti.view.description_label', category: 'voyti'));
        echo Html::div()->close();

        echo Html::div()->class('col-auto')->open();
            echo Field::buttonGroup()
                ->buttons(
                    Html::submitButton($translator->translate('voyti.view.filter_button', category: 'voyti'))->class('btn', 'btn-outline-secondary')
                );
        echo Html::div()->close();
    echo Html::div()->close();

    echo Html::form()->close();

    echo Html::div()->class('table-responsive')->open();
        echo Html::table()->class('table table-striped table-hover')->open();

        echo Html::tag('thead')->class('table-light')->open();
            echo Html::tag('tr')->open();
                echo Html::tag('th', $translator->translate('voyti.view.name_header', category: 'voyti'))->scope('col');
                echo Html::tag('th', $translator->translate('voyti.view.description_header', category: 'voyti'))->scope('col');
                echo Html::tag('th', $translator->translate('voyti.view.children_header', category: 'voyti'))->scope('col');
                echo Html::tag('th', $translator->translate('voyti.view.actions_header', category: 'voyti'))->class('text-end')->scope('col');
            echo Html::tag('tr')->close();
        echo Html::tag('thead')->close();

        echo Html::tag('tbody')->open();

        foreach ($items as $role) {
            echo Html::tag('tr')->open();
                echo Html::tag('td', Html::encode($role->getName()));
                echo Html::tag('td', Html::encode($role->getDescription()));
                echo Html::tag('td', Html::encode(implode(', ', array_map(fn ($c) => $c->getName(), $role->getChildren()))));
                echo Html::tag('td')->class('text-end')->open();
                    echo Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/roles-update', ['name' => $role->getName()]))->class('btn', 'btn-sm', 'btn-outline-secondary');
                    echo ' ';

                    echo Html::form()
                        ->post($url->generate('voyti/roles-delete', ['name' => $role->getName()]))
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
