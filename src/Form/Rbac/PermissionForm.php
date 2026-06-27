<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Rbac;

use Yiisoft\Translator\TranslatorInterface;

final class PermissionForm extends AbstractAuthItemForm
{
    public function __construct(
        TranslatorInterface $translator,
    ) {
        parent::__construct($translator);
    }

    /**
     * @return string
     *
     * @psalm-return 'permission'
     */
    #[\Override]
    public function getFormName(): string
    {
        return 'permission';
    }

    /**
     * @return string
     *
     * @psalm-return 'permission'
     */
    #[\Override]
    public function getType(): string
    {
        return 'permission';
    }
}
