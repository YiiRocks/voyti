<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var User $user
 * @var UserProfile|null $userProfile
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var \YiiRocks\Voyti\ModuleConfig $config
 * @var bool $isSwitched
 * @var int $currentUserId
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($user->getUsername());

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_admin-menu', ['url' => $url, 'translator' => $translator]);

echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
echo Html::H1($user->getUsername());

echo Html::div()->open();
echo Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/admin-update', ['id' => $user->getId()]))->class('btn', 'btn-outline-secondary', 'btn-sm', 'me-1');
echo Html::a($translator->translate('voyti.view.update_profile_link', category: 'voyti'), $url->generate('voyti/admin-update-profile', ['id' => $user->getId()]))->class('btn', 'btn-outline-secondary', 'btn-sm', 'me-1');
echo Html::a($translator->translate('voyti.view.admin.sessions_link', category: 'voyti'), $url->generate('voyti/admin-session-history', ['id' => $user->getId()]))->class('btn', 'btn-outline-secondary', 'btn-sm');
if ($config->enableSwitchIdentities && !$isSwitched) {
    $switchDisabled = $user->isBlocked() || (int) $user->getId() === $currentUserId;
    echo Html::form()
        ->post($url->generate('voyti/admin-switch', ['id' => $user->getId()]))
        ->csrf($csrf)
        ->open();
    echo Html::submitButton($translator->translate('voyti.view.admin.switch_button', category: 'voyti'))
        ->class('btn', 'btn-outline-secondary', 'btn-sm', 'me-1')
        ->disabled($switchDisabled);
    echo Html::form()->close();
}
echo Html::div()->close();
echo Html::div()->close();

/** @psalm-suppress InvalidScope */
echo $this->render('../shared/view_profile', [
    'user' => $user,
    'userProfile' => $userProfile,
    'translator' => $translator,
    'showAdminFields' => true,
    'profilePreviewClass' => 'list-group list-group-flush',
]);

echo Html::div()->close();
