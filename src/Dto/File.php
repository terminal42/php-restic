<?php

declare(strict_types=1);

namespace Terminal42\Restic\Dto;

class File
{
    public function __construct(
        private readonly string $path,
        private readonly bool $isFile,
        private readonly int $size,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isFile(): bool
    {
        return $this->isFile;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function toString(): string
    {
        return \sprintf('%s [isFile: %s, size: %d]', $this->path, $this->isFile ? 'true' : 'false', $this->size);
    }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'isFile' => $this->isFile,
            'size' => $this->size,
        ];
    }
}
