<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Terminal42\Restic\Action\Result\RestoreBackupResult;

class RestoreBackup extends AbstractAction
{
    private RestoreBackupResult $result;

    public function __construct(
        private readonly string $snapshotId,
        private readonly string $targetPath,
        private readonly bool $dryRun,
        private readonly array $paths = [],
    ) {
    }

    public function getResult(): RestoreBackupResult
    {
        return $this->result;
    }

    protected function doGetArguments(): array
    {
        $arguments = [
            'restore',
            '--json',
        ];

        $arguments[] = '--target';
        $arguments[] = $this->targetPath;

        if ($this->dryRun) {
            $arguments[] = '--dry-run';
        }

        if ([] !== $this->paths) {
            $arguments[] = '--include';

            foreach ($this->paths as $path) {
                $arguments[] = $path;
            }
        }

        $arguments[] = $this->snapshotId;

        return $arguments;
    }

    protected function updateResult(string $output, ProcessFailedException|null $exception = null): void
    {
        $this->result = new RestoreBackupResult($output, $exception);
    }
}
