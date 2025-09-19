<?php

declare(strict_types=1);

namespace Terminal42\Restic;

use Symfony\Component\Console\Style\SymfonyStyle;
use Terminal42\Restic\Action\CreateBackup;
use Terminal42\Restic\Action\ForgetOldSnapshots;
use Terminal42\Restic\Action\ListFiles;
use Terminal42\Restic\Action\ListSnapshots;
use Terminal42\Restic\Dto\FileCollection;
use Terminal42\Restic\Dto\SnapshotCollection;

/**
 * Simple high-level service for common use cases when working with the Restic instance.
 * Use Restic itself if you need more low-level operations and configurability.
 */
class Toolkit
{
    public function __construct(private readonly Restic $restic)
    {
    }

    /**
     * @return bool True on success, false on error
     */
    public function createBackup(bool $dryRun = false, \DateTimeInterface|null $time = null, SymfonyStyle|null $io = null): bool
    {
        $action = new CreateBackup(
            $this->restic->getItemsToBackup(),
            $this->restic->getExcludesFromBackup(),
            null === $io, // no-scan if no $io
            $dryRun,
            $time,
        );

        $this->restic->runAction($action, $io);

        if ($action->getResult()->wasSuccessful()) {
            if ($io) {
                $action->getResult()->addSummaryToOutput($io);
            }

            return true;
        }

        $io?->error($action->getResult()->getOutput());

        return false;
    }

    public function forgetOldBackups(): void
    {
        $action = new ForgetOldSnapshots();
        $this->restic->runAction($action);
    }

    /**
     * @param int $limit 0 disables the limit (beware of performance!)
     */
    public function listSnapshots(int $limit = 20): SnapshotCollection
    {
        $action = new ListSnapshots($limit);
        $this->restic->runAction($action);

        // No need for special error reporting, we log all unsuccessful commands
        if (!$action->getResult()->wasSuccessful()) {
            return SnapshotCollection::empty();
        }

        return $action->getResult()->getSnapshots();
    }

    public function listFiles(string $snapshotId, bool $recursive): FileCollection
    {
        $action = new ListFiles($snapshotId, $recursive);
        $this->restic->runAction($action);

        return $action->getResult()->getFiles();
    }

    public function getResticVersion(): string
    {
        return $this->restic->getResticVersion();
    }
}
