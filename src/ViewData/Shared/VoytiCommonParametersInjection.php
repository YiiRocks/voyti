<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Shared;

use Override;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\View\Renderer\CommonParametersInjectionInterface;

/**
 * Supplies every rendered voyti view with core parameters: resolved session flash messages as
 * {@see FlashViewData}, and a voyti-category-bound {@see TranslatorInterface}. Injected per-render
 * by {@see RenderTrait} to ensure availability across all voyti templates without collision risk.
 */
final class VoytiCommonParametersInjection implements CommonParametersInjectionInterface
{
    public function __construct(
        private FlashInterface $flash,
        private TranslatorInterface $translator,
    ) {}

    #[Override]
    public function getCommonParameters(): array
    {
        return [
            'flash' => new FlashViewData($this->flash),
            'translator' => $this->translator->withDefaultCategory('voyti'),
        ];
    }
}
