<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Service\MailService;
use Yiisoft\Mailer\MessageInterface;
use Yiisoft\Mailer\SendResults;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\Translator;

final class MailServiceTest extends TestCase
{
    public function testCustomMailPathIsUsed(): void
    {
        $mailPath = $this->createTempMailPath();
        try {
            file_put_contents(
                $mailPath . '/welcome.php',
                <<<'PHP'
<?php
echo 'custom-html-' . $username;
PHP
            );
            mkdir($mailPath . '/text');
            file_put_contents(
                $mailPath . '/text/welcome.php',
                <<<'PHP'
<?php
echo 'custom-text-' . $username;
PHP
            );

            $mailer = new MailCapture();
            $url = $this->createStub(UrlGeneratorInterface::class);
            $service = new MailService($mailer, $mailPath, ['fromEmail' => 'from@example.com'], new Translator('en'), $url);

            $user = new User();
            $user->setUsername('alice');
            $user->setEmail('alice@example.com');

            self::assertTrue($service->sendWelcome($user, 'secret'));

            $messages = $mailer->messages();
            self::assertCount(1, $messages);
            $message = $messages[0];
            self::assertInstanceOf(MessageInterface::class, $message);
            self::assertSame('custom-html-alice', $message->getHtmlBody());
            self::assertSame('custom-text-alice', $message->getTextBody());
        } finally {
            $this->removeTempMailPath($mailPath);
        }
    }

    private function createTempMailPath(): string
    {
        $path = sys_get_temp_dir() . '/voyti-mail-' . bin2hex(random_bytes(4));
        mkdir($path);
        return $path;
    }

    private function removeTempMailPath(string $path): void
    {
        if (is_file($path . '/text/welcome.php')) {
            unlink($path . '/text/welcome.php');
        }
        if (is_dir($path . '/text')) {
            rmdir($path . '/text');
        }
        if (is_file($path . '/welcome.php')) {
            unlink($path . '/welcome.php');
        }
        if (is_dir($path)) {
            rmdir($path);
        }
    }
}

final class MailCapture implements \Yiisoft\Mailer\MailerInterface
{
    /** @var list<MessageInterface> */
    private array $messages = [];

    public function send(MessageInterface $message): void
    {
        $this->messages[] = $message;
    }

    public function sendMultiple(array $messages): SendResults
    {
        foreach ($messages as $message) {
            $this->send($message);
        }

        return new SendResults($this->messages, []);
    }

    /**
     * @return list<MessageInterface>
     */
    public function messages(): array
    {
        return $this->messages;
    }
}
