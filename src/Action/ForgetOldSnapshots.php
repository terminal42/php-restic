<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Terminal42\Restic\Action\Result\ForgetOldSnapshotsResult;

class ForgetOldSnapshots extends AbstractAction
{
    private ForgetOldSnapshotsResult $result;

    public function __construct(
        private readonly int $keepHourly = 24,
        private readonly int $keepDaily = 7,
        private readonly int $keepWeekly = 4,
        private readonly int $keepMonthly = 12,
        private readonly int $keepYearly = 5,
    ) {
    }

    public function getResult(): ForgetOldSnapshotsResult
    {
        return $this->result;
    }

    protected function doGetArguments(): array
    {
        return [
            'forget',
            '--prune', // Automatically run the 'prune' command if snapshots have been removed
            '--cleanup-cache', // Auto remove old cache directories
            '--keep-hourly',
            $this->keepHourly,
            '--keep-daily',
            $this->keepDaily,
            '--keep-weekly',
            $this->keepWeekly,
            '--keep-monthly',
            $this->keepMonthly,
            '--keep-yearly',
            $this->keepYearly,
        ];
    }

    protected function updateResult(string $output, ProcessFailedException|null $exception = null): void
    {
        $this->result = new ForgetOldSnapshotsResult($output, $exception);
    }
}
