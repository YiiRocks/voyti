<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use Closure;
use Override;
use Yiisoft\Data\Reader\CountableDataInterface;
use Yiisoft\Data\Reader\LimitableDataInterface;
use Yiisoft\Data\Reader\OffsetableDataInterface;
use Yiisoft\Data\Reader\ReadableDataInterface;

/**
 * Maps the items returned by a readable, countable, limitable, and offsetable data reader.
 *
 * The optional preparation callback receives the current page before individual items are mapped.
 * This allows view-row mapping to perform page-level work, such as resolving related usernames in one query.
 *
 * @template TKey of array-key
 * @template TInput of array|object
 * @template TOutput of array|object
 * @template TContext
 *
 * @implements ReadableDataInterface<TKey, TOutput>
 * @implements LimitableDataInterface<TKey, TOutput>
 * @implements OffsetableDataInterface<TKey, TOutput>
 */
final readonly class MappedPaginatedDataReader implements
    ReadableDataInterface,
    CountableDataInterface,
    LimitableDataInterface,
    OffsetableDataInterface
{
    public function __construct(
        /** @var ReadableDataInterface<TKey, TInput>&CountableDataInterface&LimitableDataInterface<TKey, TInput>&OffsetableDataInterface<TKey, TInput> */
        private readonly ReadableDataInterface&CountableDataInterface&LimitableDataInterface&OffsetableDataInterface $reader,
        /** @var Closure(TInput, TContext|null): TOutput */
        private readonly Closure $mapper,
        /** @var Closure(array<array-key, TInput>): TContext|null */
        private readonly ?Closure $prepare = null,
    ) {}

    #[Override]
    /** @return iterable<TKey, TOutput> */
    public function read(): iterable
    {
        $items = iterator_to_array($this->reader->read(), true);
        $context = $this->prepare === null ? null : ($this->prepare)($items);

        foreach ($items as $key => $item) {
            yield $key => ($this->mapper)($item, $context);
        }
    }

    #[Override]
    public function readOne(): array|object|null
    {
        foreach ($this->read() as $item) {
            return $item;
        }

        return null;
    }

    #[Override]
    public function count(): int
    {
        return $this->reader->count();
    }

    #[Override]
    public function withLimit(?int $limit): static
    {
        return new self($this->reader->withLimit($limit), $this->mapper, $this->prepare);
    }

    #[Override]
    public function getLimit(): ?int
    {
        return $this->reader->getLimit();
    }

    #[Override]
    public function withOffset(int $offset): static
    {
        return new self($this->reader->withOffset($offset), $this->mapper, $this->prepare);
    }

    #[Override]
    public function getOffset(): int
    {
        return $this->reader->getOffset();
    }
}
