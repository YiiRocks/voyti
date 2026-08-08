<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use RuntimeException;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\MessageInterface;
use Yiisoft\Mailer\SendResults;

/**
 * A mailer that throws on send, letting tests exercise MailService's send-failure branch (which
 * catches the throwable and returns false) without mocking the final MailService. Pass $succeedFor
 * to let that many sends succeed before failures begin.
 */
final class FailingMailer implements MailerInterface
{
    private int $sent = 0;

    public function __construct(private readonly int $succeedFor = 0) {}

    public function compose(): void {}

    public function send(MessageInterface $message): void
    {
        if ($this->sent++ >= $this->succeedFor) {
            throw new RuntimeException('Simulated mail failure');
        }
    }

    public function sendMultiple(array $messages): SendResults
    {
        $success = [];
        $fail = [];

        foreach ($messages as $message) {
            try {
                $this->send($message);
                $success[] = $message;
            } catch (RuntimeException $e) {
                $fail[] = ['message' => $message, 'error' => $e];
            }
        }

        return new SendResults($success, $fail);
    }
}
