<?php

declare(strict_types=1);

namespace Terminal42\Restic\Dto;

class SnapshotCollection extends AbstractCollection
{
    public function latest(): Snapshot|null
    {
        $latest = null;

        /** @var Snapshot $snapshot */
        foreach ($this->items as $snapshot) {
            if (null === $latest || $snapshot->getTime() > $latest) {
                $latest = $snapshot;
            }
        }

        return $latest;
    }

    public function toArray(bool $flat = false): array
    {
        $collection = [];

        /** @var Snapshot $snapshot */
        foreach ($this->items as $snapshot) {
            $collection[] = $flat ? $snapshot->toString() : $snapshot->toArray();
        }

        return $collection;
    }
}
