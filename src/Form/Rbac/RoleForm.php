<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Rbac;

use Yiisoft\Translator\TranslatorInterface;

final class RoleForm extends AbstractAuthItemForm
{
    public function __construct(
        TranslatorInterface $translator,
    ) {
        parent::__construct($translator);
    }

    /**
     * @return string
     *
     * @psalm-return 'role'
     */
    #[\Override]
    public function getFormName(): string
    {
        return 'role';
    }

    /**
     * @return string
     *
     * @psalm-return 'role'
     */
    #[\Override]
    public function getType(): string
    {
        return 'role';
    }
}
