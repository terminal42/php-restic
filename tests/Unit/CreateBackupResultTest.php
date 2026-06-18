<?php

declare(strict_types=1);

namespace Terminal42\Restic\Test\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpProcess;
use Terminal42\Restic\Action\Result\CreateBackupResult;
use Terminal42\Restic\Action\Result\ForgetOldSnapshotsResult;

class CreateBackupResultTest extends TestCase
{
    public function testExitCodeThreeIsTreatedAsSuccessfulBackup(): void
    {
        $result = CreateBackupResult::withSummary(
            '{"message_type":"summary","snapshot_id":"abc123"}',
            [
                'snapshot_id' => 'abc123',
                'files_new' => 0,
                'files_changed' => 0,
                'files_unmodified' => 0,
                'dirs_new' => 0,
                'dirs_changed' => 0,
                'dirs_unmodified' => 0,
            ],
            $this->createExitCodeThreeException(),
        );

        $this->assertTrue($result->wasSuccessful());
        $this->assertTrue($result->hasWarnings());
        $this->assertSame('abc123', $result->getSnapshotId());
    }

    public function testExitCodeThreeIsTreatedAsSuccessfulForget(): void
    {
        $result = new ForgetOldSnapshotsResult(
            '{"message_type":"summary"}',
            $this->createExitCodeThreeException(),
        );

        $this->assertTrue($result->wasSuccessful());
        $this->assertTrue($result->hasWarnings());
    }

    private function createExitCodeThreeException(): ProcessFailedException
    {
        $process = new PhpProcess('<?php exit(3);');

        try {
            $process->mustRun();
            $this->fail('Expected the process to fail with exit code 3.');
        } catch (ProcessFailedException $exception) {
            return $exception;
        }
    }
}
