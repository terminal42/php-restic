<?php

declare(strict_types=1);

namespace Terminal42\Restic\Dto;

/**
 * @extends AbstractCollection<File>
 */
class FileCollection extends AbstractCollection
{
    public function sortByPath(): self
    {
        $collection = clone $this;
        usort($collection->items, static fn (File $a, File $b) => strcmp($a->getPath(), $b->getPath()));

        return $collection;
    }

    /**
     * @return ($flat is true ? list<string> : list<array{path: string, isFile: bool, size: int}>)
     */
    public function toArray(bool $flat = false): array
    {
        $collection = [];

        /** @var File $file */
        foreach ($this->items as $file) {
            $collection[] = $flat ? $file->toString() : $file->toArray();
        }

        return $collection;
    }
}
