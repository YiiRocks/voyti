<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Form\Theme\ThemeContainer;

/**
 * Resolves the CSS class for link-styled buttons (Html::a rendered to look like a button, e.g.
 * "Create user") from the host app's configured yiisoft/form theme, so they match whatever
 * styling the host gives Field::submitButton() elsewhere. Voyti ships no theme of its own, so
 * this resolves to an empty string until a host configures yiisoft/form.themes.
 */
final class LinkButtonHelper
{
    public static function submitButtonClass(): string
    {
        $config = ThemeContainer::getTheme()?->getFieldConfig(SubmitButton::class) ?? [];
        /** @psalm-suppress MixedAssignment */
        $class = $config['buttonClass()'][0] ?? null;

        return is_string($class) ? $class : '';
    }
}
