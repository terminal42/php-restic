<?php

declare(strict_types=1);

namespace Terminal42\Restic\Exception;

use Symfony\Component\Process\Exception\ProcessFailedException;

class CouldNotRunCommandException extends ProcessFailedException implements ExceptionInterface
{
}
