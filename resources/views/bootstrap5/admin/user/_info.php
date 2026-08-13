<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var array{
 *   menu: list<array{label: string, url: string, alignEnd: bool, routeName: string|null}>,
 *   username: string,
 *   profile: array{
 *     displayName: string,
 *     gravatarUrl: string|null,
 *     showAdminFields: bool,
 *     email: string|null,
 *     registeredDisplay: string|null,
 *     statusLabel: string|null,
 *     statusBadgeClass: string|null,
 *     publicEmail: string|null,
 *     location: string|null,
 *     website: string|null,
 *     timezone: string|null,
 *     bio: string|null,
 *     profilePreviewClass: string,
 *   },
 * } $data
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($data['username']);

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['menu' => $data['menu']]);

echo Html::H1($data['username']);

/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/view_profile', ['profile' => $data['profile'], 'translator' => $translator]);

echo Html::div()->close();
