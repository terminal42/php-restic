<?php

declare(strict_types=1);

namespace Terminal42\Restic\Dto;

abstract class AbstractCollection implements \Countable, \IteratorAggregate
{
    public function __construct(protected array $items = [])
    {
    }

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
