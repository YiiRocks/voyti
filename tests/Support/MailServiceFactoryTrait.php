<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use YiiRocks\Voyti\Service\MailService;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
use Yiisoft\View\View;

/**
 * Builds a real MailService for tests instead of mocking the final class. Pair with MailCapture to
 * assert on sent mail, or FailingMailer to exercise the send-failure branch.
 */
trait MailServiceFactoryTrait
{
    private function createMailService(MailerInterface $mailer): MailService
    {
        $translator = new Translator('en', null, 'voyti');
        $translator->addCategorySources(
            new CategorySource(
                'voyti',
                new MessageSource(dirname(__DIR__, 2) . '/resources/messages'),
                new SimpleMessageFormatter(),
            ),
        );

        return new MailService(
            $mailer,
            dirname(__DIR__, 2) . '/resources/mail',
            new View(),
            $translator,
            new FakeUrlGenerator(),
            'App',
        );
    }
}
