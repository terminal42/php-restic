<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Terminal42\Restic\Action\Result\ListFilesResult;

class ListFiles extends AbstractAction
{
    private ListFilesResult $result;

    public function __construct(
        private readonly string $snapshotId,
        private readonly bool $recursive,
        private readonly array $dirs = [],
    ) {
    }

    public function getResult(): ListFilesResult
    {
        return $this->result;
    }

    protected function doGetArguments(): array
    {
        $arguments = [
            'ls',
            '--json',
        ];

        if ($this->recursive) {
            $arguments[] = '--recursive';
        }

        $arguments[] = $this->snapshotId;

        if ($this->dirs) {
            $arguments = array_merge($arguments, $this->dirs);
        }

        return $arguments;
    }

    protected function updateResult(string $output, ProcessFailedException|null $exception = null): void
    {
        $this->result = new ListFilesResult($output, $exception);
    }
}
