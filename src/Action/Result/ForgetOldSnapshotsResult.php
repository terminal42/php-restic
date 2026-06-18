<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action\Result;

class ForgetOldSnapshotsResult extends AbstractActionResult
{
    protected function getWarningOnlyExitCodes(): array
    {
        return [3];
    }
}
