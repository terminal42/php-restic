<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Terminal42\Restic\Action\Result\ListSnapshotsResult;

class ListSnapshots extends AbstractAction
{
    private ListSnapshotsResult $result;

    public function __construct(private readonly int $limit = 20)
    {
    }

    public function getResult(): ListSnapshotsResult
    {
        return $this->result;
    }

    protected function doGetArguments(): array
    {
        $arguments = [
            'snapshots',
            '--json',
        ];

        if ($this->limit > 0) {
            $arguments[] = '--latest';
            $arguments[] = $this->limit;
        }

        return $arguments;
    }

    protected function updateResult(string $output, ProcessFailedException|null $exception = null): void
    {
        $this->result = new ListSnapshotsResult($output, $exception);
    }
}
