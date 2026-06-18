<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Widget;

use Yiisoft\Html\Html;

final class AssignmentsWidget
{
    public function render(array $available, array $assigned, string $formName = 'assignments'): string
    {
        $html = '<div class="assignments">';
        $html .= '<ul>';
        foreach ($available as $name => $description) {
            $checked = isset($assigned[$name]) ? 'checked' : '';
            $html .= '<li>';
            $html .= '<label>';
            $html .= '<input type="checkbox" name="' . Html::encode($formName) . '[' . Html::encode($name) . ']" value="1" ' . $checked . ' />';
            $html .= ' ' . Html::encode($description ?: $name);
            $html .= '</label>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
        return $html;
    }
}
