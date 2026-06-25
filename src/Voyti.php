<?php

declare(strict_types=1);

namespace YiiRocks\Voyti;

final class Voyti
{
    public const ROOT_PATH = __DIR__;
    public const TRANSLATION_CATEGORY = 'voyti';
    public const TRANSLATOR_SOURCE = 'yiirocks/voyti.translator';
    public const SWITCH_IDENTITY_SESSION_KEY = 'voyti_original_user';
    public const VIEWS_PATH = __DIR__ . '/resources/views/bootstrap5';
    public const MAIL_PATH = __DIR__ . '/resources/mail';
}
