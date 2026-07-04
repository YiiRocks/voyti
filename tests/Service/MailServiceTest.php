<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Service\MailService;
use Yiisoft\Mailer\MessageInterface;
use Yiisoft\Mailer\SendResults;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;

final class MailServiceTest extends TestCase
{

    public function testCustomMailPathIsUsed(): void
    {
        $mailPath = $this->createTempMailPath();
        try {
            mkdir($mailPath . '/html');
            file_put_contents(
                $mailPath . '/html/welcome.php',
                <<<'PHP'
<?php
echo 'custom-html-' . $username . '-' . $translator->getLocale();
PHP
            );
            mkdir($mailPath . '/text');
            file_put_contents(
                $mailPath . '/text/welcome.php',
                <<<'PHP'
<?php
echo 'custom-text-' . $username . '-' . $translator->getLocale();
PHP
            );

            $mailer = new MailCapture();
            $url = $this->createStub(UrlGeneratorInterface::class);
            $service = new MailService($mailer, $mailPath, new Translator('en'), $url);

            $user = new User();
            $user->setUsername('alice');
            $user->setEmail('alice@example.com');

            self::assertTrue($service->sendWelcome($user, 'secret'));

            $messages = $mailer->messages();
            self::assertCount(1, $messages);
            $message = $messages[0];
            self::assertInstanceOf(MessageInterface::class, $message);
            self::assertSame('custom-html-alice-en', $message->getHtmlBody());
            self::assertSame('custom-text-alice-en', $message->getTextBody());
        } finally {
            $this->removeTempMailPath($mailPath);
        }
    }

    public function testGetMailSubjectBuildsPrefixedTranslationId(): void
    {
        $translator = new RecordingTranslator();
        $service = new MailService(new MailCapture(), $this->createTempMailPath(), $translator, $this->createStub(UrlGeneratorInterface::class), 'Voyti');

        $user = new User();
        $user->setUsername('alice');
        $user->setEmail('alice@example.com');

        self::assertTrue($service->sendWelcome($user, 'secret'));

        self::assertSame('voyti.mail.welcome_subject', $translator->ids[0]);
        self::assertSame(['app' => 'Voyti'], $translator->parametersLog[0]);
    }

    public function testSendIsPubliclyCallable(): void
    {
        $mailer = new MailCapture();
        $url = $this->createStub(UrlGeneratorInterface::class);
        $service = new MailService($mailer, $this->createTempMailPath(), new Translator('en'), $url);

        self::assertTrue($service->send('custom', 'to@example.com', 'Subject', 'nonexistent'));
        self::assertCount(1, $mailer->messages());
    }

    public function testSendReconfirmationRendersUsernameConfirmationUrlAndTranslatorLocale(): void
    {
        $mailPath = $this->createTempMailPath();
        try {
            mkdir($mailPath . '/html');
            file_put_contents(
                $mailPath . '/html/reconfirmation.php',
                <<<'PHP'
<?php
echo 'html-' . $username . '-' . $confirmationUrl . '-' . $translator->getLocale();
PHP
            );
            mkdir($mailPath . '/text');
            file_put_contents(
                $mailPath . '/text/reconfirmation.php',
                <<<'PHP'
<?php
echo 'text-' . $username . '-' . $confirmationUrl . '-' . $translator->getLocale();
PHP
            );

            $mailer = new MailCapture();
            $url = new RecordingUrlGenerator();
            $service = new MailService($mailer, $mailPath, new Translator('en'), $url);

            $user = new User();
            $user->setUsername('alice');
            $user->setEmail('alice@example.com');

            $userToken = new UserToken();
            $userToken->setCode('secret-code-123');

            self::assertTrue($service->sendReconfirmation($user, $userToken));

            $messages = $mailer->messages();
            self::assertCount(1, $messages);
            $message = $messages[0];
            self::assertInstanceOf(MessageInterface::class, $message);
            self::assertSame('html-alice-https://example.test/voyti/settings-confirm-en', $message->getHtmlBody());
            self::assertSame('text-alice-https://example.test/voyti/settings-confirm-en', $message->getTextBody());
        } finally {
            $this->removeTempMailPath($mailPath, 'reconfirmation.php');
        }
    }

    public function testSendReconfirmationUsesTokenCodeInConfirmationUrl(): void
    {
        $mailer = new MailCapture();
        $url = new RecordingUrlGenerator();
        $service = new MailService($mailer, $this->createTempMailPath(), new Translator('en'), $url);

        $user = new User();
        $user->setUsername('alice');
        $user->setEmail('alice@example.com');

        $userToken = new UserToken();
        $userToken->setCode('secret-code-123');

        self::assertTrue($service->sendReconfirmation($user, $userToken));

        self::assertSame('voyti/settings-confirm', $url->name);
        self::assertSame(['code' => 'secret-code-123'], $url->arguments);
    }

    private function createTempMailPath(): string
    {
        $path = sys_get_temp_dir() . '/voyti-mail-' . bin2hex(random_bytes(4));
        mkdir($path);
        return $path;
    }

    private function removeTempMailPath(string $path, string $viewFile = 'welcome.php'): void
    {
        if (is_file($path . '/html/' . $viewFile)) {
            unlink($path . '/html/' . $viewFile);
        }
        if (is_dir($path . '/html')) {
            rmdir($path . '/html');
        }
        if (is_file($path . '/text/' . $viewFile)) {
            unlink($path . '/text/' . $viewFile);
        }
        if (is_dir($path . '/text')) {
            rmdir($path . '/text');
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

    /**
     * @return list<MessageInterface>
     */
    public function messages(): array
    {
        return $this->messages;
    }

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
}

final class RecordingTranslator implements TranslatorInterface
{
    /** @var list<string> */
    public array $ids = [];

    /** @var list<array<string, scalar>> */
    public array $parametersLog = [];

    public function addCategorySources(\Yiisoft\Translator\CategorySource ...$categories): static
    {
        return $this;
    }

    public function getLocale(): string
    {
        return 'en';
    }

    public function setLocale(string $locale): static
    {
        return $this;
    }

    public function translate(
        string|\Stringable $id,
        array $parameters = [],
        ?string $category = null,
        ?string $locale = null
    ): string {
        $this->ids[] = (string) $id;
        $this->parametersLog[] = $parameters;
        return (string) $id;
    }

    public function withDefaultCategory(string $category): static
    {
        return $this;
    }

    public function withLocale(string $locale): static
    {
        return $this;
    }
}

final class RecordingUrlGenerator implements UrlGeneratorInterface
{
    public array $arguments = [];
    public string $name = '';

    public function generate(
        string $name,
        array $arguments = [],
        array $queryParameters = [],
        ?string $hash = null,
    ): string {
        return $this->generateAbsolute($name, $arguments, $queryParameters, $hash);
    }

    public function generateAbsolute(
        string $name,
        array $arguments = [],
        array $queryParameters = [],
        ?string $hash = null,
        ?string $scheme = null,
        ?string $host = null
    ): string {
        $this->name = $name;
        $this->arguments = $arguments;
        return 'https://example.test/' . $name;
    }

    public function generateFromCurrent(
        array $replacedArguments,
        array $queryParameters = [],
        ?string $hash = null,
        ?string $fallbackRouteName = null
    ): string {
        return '';
    }

    public function getUriPrefix(): string
    {
        return '';
    }

    public function setDefaultArgument(string $name, bool|float|int|string|\Stringable|null $value): void
    {
    }

    public function setUriPrefix(string $name): void
    {
    }
}
