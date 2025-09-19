<?php

declare(strict_types=1);

namespace Terminal42\Restic\Test\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Terminal42\Restic\Restic;
use Terminal42\Restic\Toolkit;

abstract class AbstractFunctionalTestCase extends TestCase
{
    private string $backupPath = __DIR__.'/../../var/backup';

    private string $restorePath = __DIR__.'/../../var/restore';

    protected function createRestic(string $backupDirectory, array $excludes = [], string|null $backupPath = null): Restic
    {
        $backupPath = $backupPath ?? $this->backupPath;
        $backupPath = $this->ensureDirectoryExistsAndIsEmpty($backupPath);

        (new Filesystem())->remove($backupDirectory.'/var');

        return Restic::create(
            $backupDirectory,
            $backupPath,
            '12345678',
            $excludes,
        );
    }

    protected function createToolkit(string $backupDirectory, array $excludes = [], string|null $backupPath = null): Toolkit
    {
        return new Toolkit($this->createRestic($backupDirectory, $excludes, $backupPath));
    }

    protected static function getFixtureDirectory(string $fixture): string
    {
        return realpath(__DIR__.'/../Fixtures/'.$fixture);
    }

    protected function getRestorePath(): string
    {
        return $this->ensureDirectoryExistsAndIsEmpty($this->restorePath);
    }

    private function ensureDirectoryExistsAndIsEmpty(string $directory): string
    {
        $fs = new Filesystem();
        $fs->remove($directory);
        $fs->mkdir($directory);

        return realpath($directory);
    }
}
