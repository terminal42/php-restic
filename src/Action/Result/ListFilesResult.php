<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action\Result;

use Terminal42\Restic\Dto\File;
use Terminal42\Restic\Dto\FileCollection;

class ListFilesResult extends AbstractActionResult
{
    public function getFiles(): FileCollection
    {
        $collection = new FileCollection();
        $lines = explode("\n", $this->getOutput());

        foreach ($lines as $line) {
            $line = json_decode(trim($line), true);

            if (
                !isset($line['message_type'])
                || 'node' !== $line['message_type']
                || !isset($line['type'])
                || !\in_array($line['type'], ['dir', 'file'], true)
            ) {
                continue;
            }

            $collection->add(new File(
                $line['path'],
                'file' === $line['type'],
                $line['size'] ?? 0,
            ));
        }

        return $collection;
    }
}
