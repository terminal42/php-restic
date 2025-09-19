<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action\Result;

class RestoreBackupResult extends AbstractActionResult
{
    public function getNumberOfFilesRestored(): int
    {
        return $this->getJsonDecodedOutput()['files_restored'] ?? 0;
    }

    public function getTotalBytes(): int
    {
        return $this->getJsonDecodedOutput()['total_bytes'] ?? 0;
    }
}
