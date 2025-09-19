<?php

declare(strict_types=1);

namespace Terminal42\Restic\Dto;

class Snapshot
{
    public function __construct(
        private readonly string $id,
        private readonly \DateTimeInterface $time,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTime(): \DateTimeInterface
    {
        return $this->time;
    }

    public function toString(): string
    {
        return \sprintf('%s [%s]', $this->id, $this->time->format(\DateTimeInterface::ATOM));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'time' => $this->time->format(\DateTimeInterface::ATOM),
        ];
    }
}
