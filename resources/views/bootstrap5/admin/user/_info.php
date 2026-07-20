<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Admin\User\InfoViewData;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var InfoViewData $data
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($data->username);

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['menu' => $data->menu]);

echo Html::H1($data->username);

/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/view_profile', ['profile' => $data->profile, 'translator' => $translator]);

echo Html::div()->close();
