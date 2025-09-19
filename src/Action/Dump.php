<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Terminal42\Restic\Action\Result\DumpResult;

class Dump extends AbstractAction
{
    private DumpResult $result;

    public function __construct(
        private readonly string $snapshotId,
        private readonly string $targetFileName,
        private readonly string $pathOrFile,
    ) {
    }

    public function getResult(): DumpResult
    {
        return $this->result;
    }

    protected function doGetArguments(): array
    {
        $arguments = [
            'dump',
        ];

        // Always use zip as archive mode for paths
        $arguments[] = '-a';
        $arguments[] = 'zip';

        $arguments[] = '--target';
        $arguments[] = $this->targetFileName;

        $arguments[] = $this->snapshotId;
        $arguments[] = $this->pathOrFile;

        return $arguments;
    }

    protected function updateResult(string $output, ProcessFailedException|null $exception = null): void
    {
        $this->result = new DumpResult($output, $exception);
    }
}
