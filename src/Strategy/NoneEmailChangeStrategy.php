<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;

final readonly class NoneEmailChangeStrategy implements EmailChangeStrategyInterface
{
    public function __construct(
        private SettingsForm $form,
    ) {
    }

    #[\Override]
    public function run(): bool
    {
        $user = $this->form->getUser();
        if ($user === null) {
            return false;
        }
        $user->setEmail($this->form->email);
        $user->save();

        return true;
    }
}
