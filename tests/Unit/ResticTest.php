<?php

declare(strict_types=1);

namespace Terminal42\Restic\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Terminal42\Restic\Restic;

class ResticTest extends TestCase
{
    #[DataProvider('determineBinaryProvider')]
    public function testDetermineBinary(string $uname, string|null $expectedBinary): void
    {
        $this->assertSame($expectedBinary, Restic::determineBinary($uname));
    }

    public static function determineBinaryProvider(): iterable
    {
        yield [
            'Nonsense',
            null,
        ];

        yield [
            'Darwin MacBook-Pro.local 23.1.0 Darwin Kernel Version 23.1.0: Mon Oct  9 21:28:45 PDT 2023; root:xnu-10002.41.9~6/RELEASE_ARM64_T6020 arm64',
            'restic_darwin_arm64',
        ];

        yield [
            'Linux server.server.com 3.10.0-962.3.2.lve1.5.79.el7.x86_64 #1 SMP Wed Mar 15 09:10:44 UTC 2023 x86_64',
            'restic_linux_amd64',
        ];
    }
}
