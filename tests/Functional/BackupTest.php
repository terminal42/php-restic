<?php

declare(strict_types=1);

namespace Terminal42\Restic\Test\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Terminal42\Restic\Dto\Snapshot;
use Terminal42\Restic\Restic;

class BackupTest extends AbstractFunctionalTestCase
{
    public function testResticVersion(): void
    {
        $toolkit = $this->createToolkit(self::getFixtureDirectory('regular-backup'));
        $this->assertSame(Restic::RESTIC_VERSION, $toolkit->getResticVersion());
    }

    /**
     * This test runs for a little over a minute!
     */
    public function testForgetsSnapshotsCorrectly(): void
    {
        $toolkit = $this->createToolkit(self::getFixtureDirectory('regular-backup'));

        // Add a yearly backup for 6 years (6 snapshots)
        foreach (range(2019, 2024) as $year) {
            $toolkit->createBackup(false, new \DateTimeImmutable($year.'-01-01 05:00:00'));
        }

        // Add 2 monthly backups for 2024 (24 snapshots)
        foreach (range(1, 12) as $month) {
            $toolkit->createBackup(false, new \DateTimeImmutable('2024-'.$month.'-01 05:00:00'));
            $toolkit->createBackup(false, new \DateTimeImmutable('2024-'.$month.'-15 05:00:00'));
        }

        // Add daily backups for December 2024 except for the 31st (30 snapshots)
        foreach (range(1, 30) as $day) {
            $toolkit->createBackup(false, new \DateTimeImmutable('2024-12-'.$day.' 05:00:00'));
        }

        // Add hourly backups for the 31st of December (23 snapshots)
        foreach (range(1, 23) as $hour) {
            $toolkit->createBackup(false, new \DateTimeImmutable('2024-12-31 '.$hour.':00:00'));
        }

        // Should be 83 snapshots now
        $this->assertCount(83, $toolkit->listSnapshots(0));

        // Forget and cleanup
        $toolkit->forgetOldBackups();

        // Should be 46 snapshots now (24 hourly, 7 daily, 4 weekly, 12 monthly and 5 yearly -> 51 snapshots whereas some overlap of course!)
        $this->assertCount(46, $toolkit->listSnapshots(0));

        $snapshotTimes = [];

        /** @var Snapshot $snapshot */
        foreach ($toolkit->listSnapshots(0)->items() as $snapshot) {
            $snapshotTimes[] = $snapshot->getTime()->format('Y-m-d H:i:s');
        }

        $this->assertSame(
            [
                '2020-01-01 05:00:00', // last 5 years
                '2021-01-01 05:00:00', // last 5 years
                '2022-01-01 05:00:00', // last 5 years
                '2023-01-01 05:00:00', // last 5 years
                '2024-01-15 05:00:00', // last 12 months
                '2024-02-15 05:00:00', // last 12 months
                '2024-03-15 05:00:00', // last 12 months
                '2024-04-15 05:00:00', // last 12 months
                '2024-05-15 05:00:00', // last 12 months
                '2024-06-15 05:00:00', // last 12 months
                '2024-07-15 05:00:00', // last 12 months
                '2024-08-15 05:00:00', // last 12 months
                '2024-09-15 05:00:00', // last 12 months
                '2024-10-15 05:00:00', // last 12 months
                '2024-11-15 05:00:00', // last 12 months
                '2024-12-15 05:00:00', // last 4 weeks
                '2024-12-22 05:00:00', // last 4 weeks
                '2024-12-25 05:00:00', // last 7 days
                '2024-12-26 05:00:00', // last 7 days
                '2024-12-27 05:00:00', // last 7 days
                '2024-12-28 05:00:00', // last 7 days
                '2024-12-29 05:00:00', // last 7 days
                '2024-12-30 05:00:00', // last 7 days
                '2024-12-31 01:00:00', // last 24 hours
                '2024-12-31 02:00:00', // last 24 hours
                '2024-12-31 03:00:00', // last 24 hours
                '2024-12-31 04:00:00', // last 24 hours
                '2024-12-31 05:00:00', // last 24 hours
                '2024-12-31 06:00:00', // last 24 hours
                '2024-12-31 07:00:00', // last 24 hours
                '2024-12-31 08:00:00', // last 24 hours
                '2024-12-31 09:00:00', // last 24 hours
                '2024-12-31 10:00:00', // last 24 hours
                '2024-12-31 11:00:00', // last 24 hours
                '2024-12-31 12:00:00', // last 24 hours
                '2024-12-31 13:00:00', // last 24 hours
                '2024-12-31 14:00:00', // last 24 hours
                '2024-12-31 15:00:00', // last 24 hours
                '2024-12-31 16:00:00', // last 24 hours
                '2024-12-31 17:00:00', // last 24 hours
                '2024-12-31 18:00:00', // last 24 hours
                '2024-12-31 19:00:00', // last 24 hours
                '2024-12-31 20:00:00', // last 24 hours
                '2024-12-31 21:00:00', // last 24 hours
                '2024-12-31 22:00:00', // last 24 hours
                '2024-12-31 23:00:00', // last 24 hours
            ],
            $snapshotTimes,
        );
    }

    public function testDump(): void
    {
        $restorePath = $this->getRestorePath();
        $toolkit = $this->createToolkit(self::getFixtureDirectory('regular-backup'));
        $snapshotId = $toolkit->createBackup();

        // Test dumping a file
        $toolkit->dumpPathOrFile($snapshotId, 'src/Controller/LuckyController.php', $restorePath.'/LuckyController.php');
        $this->assertFileEquals(
            self::getFixtureDirectory('regular-backup').'/src/Controller/LuckyController.php',
            $restorePath.'/LuckyController.php',
        );

        // Test dumping an entire path
        $toolkit->dumpPathOrFile($snapshotId, 'src', $restorePath.'/test.zip');
        $this->assertFileExists($restorePath.'/test.zip');

        // Unarchive the path and assert the contents
        $zip = new \ZipArchive();
        $zip->open($restorePath.'/test.zip');
        $zip->extractTo($restorePath.'/test');
        $zip->close();

        $this->assertFileEquals(
            self::getFixtureDirectory('regular-backup').'/src/Controller/LuckyController.php',
            $restorePath.'/test/src/Controller/LuckyController.php',
        );
    }

    #[DataProvider('restoreDataProvider')]
    public function testRestore(string $projectDir, array $pathsToRestore, array $expectedRestoredFiles, int $expectedNumberOfFilesRestored, int $expectedTotalBytes): void
    {
        $restorePath = $this->getRestorePath();
        $toolkit = $this->createToolkit($projectDir);
        $snapshotId = $toolkit->createBackup();

        $result = $toolkit->restoreBackup($snapshotId, $restorePath, $pathsToRestore);

        $restoredFiles = array_values(array_map(static fn (SplFileInfo $file) => $file->getRelativePathname(), iterator_to_array(Finder::create()->in($restorePath)->sortByName())));

        $this->assertSame($expectedRestoredFiles, $restoredFiles);
        $this->assertSame($expectedNumberOfFilesRestored, $result->getNumberOfFilesRestored());
        $this->assertSame($expectedTotalBytes, $result->getTotalBytes());
    }

    public static function restoreDataProvider(): iterable
    {
        yield 'Regular backup' => [
            self::getFixtureDirectory('regular-backup'),
            [],
            [
                'config',
                'config/config.yaml',
                'src',
                'src/Controller',
                'src/Controller/LuckyController.php',
                'var',
            ],
            6,
            488,
        ];
        yield 'Regular backup with only paths' => [
            self::getFixtureDirectory('regular-backup'),
            ['src'],
            [
                'src',
                'src/Controller',
                'src/Controller/LuckyController.php',
            ],
            3,
            449,
        ];

        yield 'For symlinked and more complex setups it should work just the same but one has to provide the exact path' => [
            self::getFixtureDirectory('backup-with-releases-and-symlinks').'/releases/42',
            ['releases/42/src'],
            [
                'releases',
                'releases/42',
                'releases/42/src',
                'releases/42/src/Controller',
                'releases/42/src/Controller/LuckyController.php',
            ],
            5,
            449,
        ];
    }

    #[DataProvider('backupDataProvider')]
    public function testBackup(array $expectedFiles, string $projectDir, array $excludes = [], string|null $backupPath = null): void
    {
        $toolkit = $this->createToolkit($projectDir, $excludes, $backupPath);

        $snapshotId = $toolkit->createBackup();
        $snapshots = $toolkit->listSnapshots();
        $this->assertFalse($snapshots->isEmpty());
        $this->assertCount(1, $snapshots);

        $latestId = $snapshots->latest()->getId();
        $this->assertSame($snapshotId, $latestId);

        $files = $toolkit->listFiles($latestId, true)->sortByPath()->toArray(true);

        $this->assertSame($expectedFiles, $files);
    }

    public static function backupDataProvider(): iterable
    {
        yield 'Regular backup' => [
            [
                '/config [isFile: false, size: 0]',
                '/config/config.yaml [isFile: true, size: 39]',
                '/src [isFile: false, size: 0]',
                '/src/Controller [isFile: false, size: 0]',
                '/src/Controller/LuckyController.php [isFile: true, size: 449]',
                '/var [isFile: false, size: 0]',
            ],
            self::getFixtureDirectory('regular-backup'),
        ];

        yield 'Regular backup with excludes' => [
            [
                '/config [isFile: false, size: 0]',
                '/config/config.yaml [isFile: true, size: 39]',
                '/src [isFile: false, size: 0]',
                '/var [isFile: false, size: 0]',
            ],
            self::getFixtureDirectory('regular-backup'),
            ['src/Controller'],
        ];

        yield 'Symlinked folders outside of the project dir with symlinks to follow' => [
            [
                '/releases [isFile: false, size: 0]',
                '/releases/42 [isFile: false, size: 0]',
                '/releases/42/config [isFile: false, size: 0]',
                '/releases/42/config/config.yaml [isFile: true, size: 39]',
                '/releases/42/src [isFile: false, size: 0]',
                '/releases/42/var [isFile: false, size: 0]',
                '/shared [isFile: false, size: 0]',
                '/shared/files [isFile: false, size: 0]',
                '/shared/files/shared-file.txt [isFile: true, size: 15]',
            ],
            self::getFixtureDirectory('backup-with-releases-and-symlinks').'/releases/42',
            ['src/Controller'], // Also test that excludes work correctly and do not require the /releases/42 prefix in this case
        ];

        yield 'Symlinked folders outside of the project dir with symlinks that are excluded' => [
            [
                '/config [isFile: false, size: 0]',
                '/config/config.yaml [isFile: true, size: 39]',
                '/src [isFile: false, size: 0]',
                '/src/Controller [isFile: false, size: 0]',
                '/src/Controller/LuckyController.php [isFile: true, size: 449]',
                '/var [isFile: false, size: 0]',
            ],
            self::getFixtureDirectory('backup-with-releases-and-symlinks').'/releases/42',
            ['files'],
        ];

        yield 'Local backup within same folder must exclude itself ("var/restic-backups")' => [
            [
                '/config [isFile: false, size: 0]',
                '/config/config.yaml [isFile: true, size: 39]',
                '/src [isFile: false, size: 0]',
                '/var [isFile: false, size: 0]',
            ],
            self::getFixtureDirectory('regular-backup'),
            ['src/Controller'],
            self::getFixtureDirectory('regular-backup').'/var/restic-backups',
        ];
    }
}
