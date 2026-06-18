<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

use YiiRocks\Voyti\Form\SettingsForm;

final class InsecureEmailChangeStrategy implements MailChangeStrategyInterface
{
    public function __construct(
        private readonly SettingsForm $form,
    ) {
    }

    public function run(): bool
    {
        $user = $this->form->getUser();
        $user->setEmail($this->form->email);

        return $user->save();
    }
}
