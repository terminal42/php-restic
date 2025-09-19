#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\SymfonyStyle;
use Terminal42\Restic\Restic;

(new SingleCommandApplication())
    ->setName('Bump restic version.')
    ->addArgument('version', InputArgument::REQUIRED, 'The desired version.')
    ->setCode(
        static function (InputInterface $input, OutputInterface $output): int {
            $io = new SymfonyStyle($input, $output);
            $version = $input->getArgument('version');

            // Read the file
            $resticClassPath = __DIR__.'/../src/Restic.php';
            $content = file_get_contents($resticClassPath);
            $pattern = "/public?\\s*const\\s+RESTIC_VERSION\\s*=\\s*'[^']*';/";
            $replacement = "public const RESTIC_VERSION = '{$version}';";
            $updatedContent = preg_replace($pattern, $replacement, $content);
            file_put_contents($resticClassPath, $updatedContent);

            if (Restic::RESTIC_VERSION !== $version) {
                $io->error('Version could not be updated in Restic class.');

                return Command::FAILURE;
            }

            Restic::updateShaSumFile();

            return Command::SUCCESS;
        },
    )
    ->run()
;
