<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action\Result;

use Terminal42\Restic\Dto\Snapshot;
use Terminal42\Restic\Dto\SnapshotCollection;

class ListSnapshotsResult extends AbstractActionResult
{
    public function getSnapshots(): SnapshotCollection
    {
        $collection = new SnapshotCollection();
        $snapshots = json_decode($this->getOutput(), true);

        foreach ($snapshots as $snapshot) {
            $collection->add(new Snapshot(
                $snapshot['id'],
                new \DateTimeImmutable($snapshot['time']),
            ));
        }

        return $collection;
    }
}
