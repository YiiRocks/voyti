<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Db\Sqlite\Dsn;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;

abstract class TestCase extends BaseTestCase
{
    private ?ContainerInterface $container = null;
    private ?ConnectionInterface $db = null;
    private ?TranslatorInterface $translator = null;

    protected function createContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            /** @var array<string, object> */
            private array $services = [];

            public function set(string $id, object $service): void
            {
                $this->services[$id] = $service;
            }

            /** @return object */
            #[\Override]
            public function get(string $id): object
            {
                if (isset($this->services[$id])) {
                    return $this->services[$id];
                }
                throw new \Yiisoft\Di\NotFoundException("Service '$id' not found.");
            }

            #[\Override]
            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    protected function createSqliteConnection(): ConnectionInterface
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite extension required.');
        }
        $dsn = new Dsn('sqlite', ':memory:');
        $driver = new SqliteDriver($dsn);
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('set')->willReturn(true);
        $cache->method('get')->willReturn(null);
        $schemaCache = new SchemaCache($cache);
        $schemaCache->setEnabled(false);
        return new SqliteConnection($driver, $schemaCache);
    }

    protected function createTranslator(): TranslatorInterface
    {
        $translator = new Translator('en', null, 'voyti');
        $translator->addCategorySources(
            new CategorySource(
                'voyti',
                new MessageSource(dirname(__DIR__) . '/src/resources/messages'),
                new SimpleMessageFormatter(),
            ),
        );
        return $translator;
    }

    protected function getContainer(): ContainerInterface
    {
        if ($this->container === null) {
            $this->container = $this->createContainer();
        }
        return $this->container;
    }

    protected function getDb(): ConnectionInterface
    {
        if ($this->db === null) {
            $this->db = $this->createSqliteConnection();
        }
        return $this->db;
    }

    protected function getTranslator(): TranslatorInterface
    {
        if ($this->translator === null) {
            $this->translator = $this->createTranslator();
        }
        return $this->translator;
    }

    protected function hasSqliteConnection(): bool
    {
        return $this->db !== null;
    }

    /**
     * Normalizes CRLF line endings to LF so console output assertions are stable across platforms.
     */
    protected function normalizeLineEndings(string $value): string
    {
        return str_replace("\r\n", "\n", $value);
    }
}
