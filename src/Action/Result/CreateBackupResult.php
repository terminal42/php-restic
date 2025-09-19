<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action\Result;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CreateBackupResult extends AbstractActionResult
{
    private array $summary = [];

    public static function withSummary(string $output, array $summary, ProcessFailedException|null $exception = null)
    {
        $result = new self($output, $exception);
        $result->summary = $summary;

        return $result;
    }

    public function addSummaryToOutput(SymfonyStyle $io): void
    {
        if ([] === $this->summary) {
            return;
        }

        $io->table(
            [
                'Snapshot ID',
                'New files',
                'Changed files',
                'Unmodified files',
                'New directories',
                'Changed directories',
                'Unmodified directories',
            ],
            [[
                $this->summary['snapshot_id'],
                $this->summary['files_new'],
                $this->summary['files_changed'],
                $this->summary['files_unmodified'],
                $this->summary['dirs_new'],
                $this->summary['dirs_changed'],
                $this->summary['dirs_unmodified'],
            ]],
        );
    }
}
