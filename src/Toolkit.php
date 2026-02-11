<?php

declare(strict_types=1);

namespace Terminal42\Restic;

use Symfony\Component\Console\Style\SymfonyStyle;
use Terminal42\Restic\Action\CreateBackup;
use Terminal42\Restic\Action\Dump;
use Terminal42\Restic\Action\ForgetOldSnapshots;
use Terminal42\Restic\Action\ListFiles;
use Terminal42\Restic\Action\ListSnapshots;
use Terminal42\Restic\Action\RestoreBackup;
use Terminal42\Restic\Action\Result\RestoreBackupResult;
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

    public function reset(): void
    {
        $this->restic->reset();
    }

    /**
     * @return ?string The snapshot ID on success, null on error
     */
    public function createBackup(bool $dryRun = false, \DateTimeInterface|null $time = null, SymfonyStyle|null $io = null): string|null
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

            return $action->getResult()->getSnapshotId();
        }

        $io?->error($action->getResult()->getOutput());

        return null;
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

    public function restoreBackup(string $snapshotId, string $restorePath, array $pathsToRestore = [], bool $dryRun = false): RestoreBackupResult
    {
        $action = new RestoreBackup($snapshotId, $restorePath, $dryRun, $pathsToRestore);
        $this->restic->runAction($action);

        return $action->getResult();
    }

    /**
     * Dumps a file to the given target file name. In case of a path given, it will create a zip archive, so make sure your $targetFileName
     * contains ".zip" in that case. If a file was given, it will not compress it, so make sure you use the appropriate target file name.
     */
    public function dumpPathOrFile(string $snapshotId, string $pathOrFile, string $targetFileName): void
    {
        $action = new Dump($snapshotId, $targetFileName, $pathOrFile);
        $this->restic->runAction($action);
    }
}
