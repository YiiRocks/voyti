<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Exception;

use RuntimeException;
use YiiRocks\Voyti\Event\Auth\BeforeLoginEvent;
use YiiRocks\Voyti\Event\Auth\BeforeRegisterEvent;
use YiiRocks\Voyti\Event\User\BeforeAccountUpdateEvent;

/**
 * Thrown by a listener of a cancellable BEFORE event (e.g. {@see BeforeLoginEvent},
 * {@see BeforeRegisterEvent}, {@see BeforeAccountUpdateEvent})
 * to prevent the action it precedes. The dispatching service/controller catches it and turns it into
 * a failure result or a form error, using `$errorDetails` as the field/attribute list when present.
 */
final class ActionPreventedException extends RuntimeException
{
    /**
     * @param list<int|string> $errorDetails
     */
    public function __construct(
        string $message,
        private readonly array $errorDetails = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @return list<int|string>
     */
    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }
}
