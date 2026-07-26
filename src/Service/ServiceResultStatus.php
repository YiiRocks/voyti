<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

/**
 * Constants for the success/failure status carried by {@see ServiceResult}.
 */
final class ServiceResultStatus
{
    public const string FAILURE = 'failure';
    public const string SUCCESS = 'success';
}
