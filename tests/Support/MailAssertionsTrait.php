<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use Yiisoft\Mailer\MailerInterface;

/**
 * Assertions over the mail messages captured by {@see MailCapture} during a controller test.
 *
 * Requires the test to hold a `$container` property bound to a `TestContainerTrait`-built container.
 */
trait MailAssertionsTrait
{
    private function assertMailSent(): void
    {
        $mailer = $this->container->get(MailerInterface::class);
        self::assertInstanceOf(MailCapture::class, $mailer);
        self::assertNotEmpty($mailer->getSentMessages());
    }

    private function assertNoMailSent(): void
    {
        $mailer = $this->container->get(MailerInterface::class);
        self::assertInstanceOf(MailCapture::class, $mailer);
        self::assertEmpty($mailer->getSentMessages());
    }
}
