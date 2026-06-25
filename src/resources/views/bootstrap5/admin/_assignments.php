<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var User $user
 * @var ModuleConfig $config
 * @var array $assignments Array of assigned item names (string[])
 * @var array $available Array of unassigned items (name => Item)
 * @var TranslatorInterface $translator
 * @var UrlGeneratorInterface $url
 * @var string $csrf
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');

echo Html::div()->class('voyti-assignments')->open();
    Html::H3()->class('mb-3')->open();
        echo $translator->translate('voyti.view.assignments.title', category: 'voyti');
    echo Html::H3()->close();

    echo Html::form()
        ->post($url->generate('voyti/admin-assignments', ['id' => $user->getId()]))
        ->csrf($csrf)
        ->open();

    echo Html::table()->class('table')->open();
        echo Html::tag('thead')->open();
            echo Html::tag('tr')->open();
                echo Html::tag('th', $translator->translate('voyti.view.assignments.assigned', category: 'voyti'));
                echo Html::tag('th', $translator->translate('voyti.view.assignments.available', category: 'voyti'));
            echo Html::tag('tr')->close();
        echo Html::tag('thead')->close();
        echo Html::tag('tbody')->open();
            echo Html::tag('tr')->open();
                echo Html::tag('td')->open();
                    foreach ($assignments as $itemName) {
                        echo Html::div()->class('form-check')->open();
                            echo Html::input('checkbox')->class('form-check-input')->name('items[]')->value($itemName)->checked();
                            echo Html::label($itemName)->class('form-check-label');
                        echo Html::div()->close();
                    }
                echo Html::tag('td')->close();
                echo Html::tag('td')->open();
                    foreach ($available as $name => $item) {
                        echo Html::div()->class('form-check')->open();
                            echo Html::input('checkbox')->class('form-check-input')->name('items[]')->value($name);
                            echo Html::label($name)->class('form-check-label');
                        echo Html::div()->close();
                    }
                echo Html::tag('td')->close();
            echo Html::tag('tr')->close();
        echo Html::tag('tbody')->close();
    echo Html::table()->close();

    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.assignments.update', category: 'voyti'))->class('btn', 'btn-primary')
        );

    echo Html::form()->close();
echo Html::div()->close();
