<?php

declare(strict_types=1);

namespace Terminal42\Restic\Dto;

/**
 * @template T of object
 *
 * @implements \IteratorAggregate<array-key, T>
 *
 * @phpstan-consistent-constructor
 */
abstract class AbstractCollection implements \Countable, \IteratorAggregate
{
    /**
     * @param array<array-key, T> $items
     */
    public function __construct(protected array $items = [])
    {
    }

    /**
     * @return array<array-key, T>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function count(): int
    {
        return \count($this->items);
    }

    /**
     * @param T $item
     *
     * @return $this
     */
    public function add(object $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    public static function empty(): static
    {
        return new static([]);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
