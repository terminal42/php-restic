<?php

declare(strict_types=1);

namespace Terminal42\Restic;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Terminal42\Restic\Action\AbstractAction;
use Terminal42\Restic\Action\GetVersion;
use Terminal42\Restic\Action\InitRepository;
use Terminal42\Restic\Exception\CouldNotDetermineBinaryException;
use Terminal42\Restic\Exception\CouldNotDetermineResticVersionException;
use Terminal42\Restic\Exception\CouldNotDownloadException;
use Terminal42\Restic\Exception\CouldNotExtractBinaryException;
use Terminal42\Restic\Exception\CouldNotInitializeResticRespositoryException;
use Terminal42\Restic\Exception\CouldNotVerifyFileIntegrityException;
use Terminal42\Restic\Exception\ExceptionInterface;

final class Restic
{
    public const RESTIC_VERSION = '0.18.0';

    private const RESTIC_BINARY_NAME = 'restic_'.self::RESTIC_VERSION;

    private const DOWNLOAD_URL = 'https://github.com/restic/restic/releases/download/v{version}/';

    private const SHA_SUMS_FILE = __DIR__.'/../config/restic_sha256sums.txt';

    private const BINARY_LIST = [
        'restic_darwin_amd64' => 'restic_{version}_darwin_amd64.bz2',
        'restic_darwin_arm64' => 'restic_{version}_darwin_arm64.bz2',
        'restic_windows_386' => 'restic_{version}_windows_386.zip',
        'restic_windows_amd64' => 'restic_{version}_windows_amd64.zip',
        'restic_freebsd_386' => 'restic_{version}_freebsd_386.bz2',
        'restic_freebsd_amd64' => 'restic_{version}_freebsd_amd64.bz2',
        'restic_freebsd_arm' => 'restic_{version}_freebsd_arm.bz2',
        'restic_linux_386' => 'restic_{version}_linux_386.bz2',
        'restic_linux_amd64' => 'restic_{version}_linux_amd64.bz2',
        'restic_linux_arm' => 'restic_{version}_linux_arm.bz2',
        'restic_linux_arm64' => 'restic_{version}_linux_arm64.bz2',
    ];

    private const OS_MATCHER = [
        'darwin' => 'darwin',
        'win' => 'windows',
        'freebsd' => 'freebsd',
        'linux' => 'linux',
    ];

    private const ARCH_MATCHER = [
        // 64-bit AMD
        'amd64' => 'amd64',
        'x64' => 'amd64',
        'x86_64' => 'amd64',

        // 64-bit ARM
        'arm64' => 'arm64',

        // 32-bit 386
        '386' => '386',

        // 32-bit AR
        'arm' => 'arm',
    ];

    private array $backupItemsConfig = [];

    private function __construct(
        private readonly string $backupDirectory,
        private readonly string $resticDirectory,
        #[\SensitiveParameter]
        private readonly array $resticRepoAccessEnv,
        private array $excludes,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string          $backupDirectory The absolute path to the directory you want to backup. Usually your project directory.
     * @param string          $repository      the string DSN for the Restic repository, only absolute local paths or S3 are allowed at the moment
     * @param string          $password        The password of the Restic repository
     * @param array           $excludes        an array of excludes, must be **relative** to the backup directory
     * @param array           $env             An array of Restic env vars. AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY for S3 for example.
     * @param string          $resticDirectory The name where the Restic binary is downloaded to. Relative to the backup directory (will be excluded from backups automatically). Default: var/restic
     * @param LoggerInterface $logger          An optional PSR logger
     */
    public static function create(string $backupDirectory, #[\SensitiveParameter] string $repository, #[\SensitiveParameter] string $password, array $excludes, #[\SensitiveParameter] array $env = [], string $resticDirectory = 'var/restic', LoggerInterface $logger = new NullLogger()): self
    {
        \assert('' !== $repository, 'The repository cannot be empty.');
        \assert('' !== $password, 'The password cannot be empty.');

        $scheme = parse_url($repository, PHP_URL_SCHEME);

        // Local backup repository
        if (empty($scheme)) {
            if (!Path::isAbsolute($repository)) {
                throw new \InvalidArgumentException('Local repository must be absolute path to ensure it is always in the same place.');
            }

            // Add the repository to the excludes so we're never backing up ourselves somehow
            $excludes[] = $repository;
        }

        if ('s3' === $scheme) {
            \assert(\array_key_exists('AWS_ACCESS_KEY_ID', $env) && '' !== $env['AWS_ACCESS_KEY_ID'], 'Cannot use s3 without AWS_ACCESS_KEY_ID environment variable.');
            \assert(\array_key_exists('AWS_SECRET_ACCESS_KEY', $env) && '' !== $env['AWS_SECRET_ACCESS_KEY'], 'Cannot use s3 without AWS_SECRET_ACCESS_KEY environment variable.');
        }

        $env['RESTIC_REPOSITORY'] = $repository;
        $env['RESTIC_PASSWORD'] = $password;

        return new self(
            $backupDirectory,
            $resticDirectory,
            $env,
            $excludes,
            $logger,
        );
    }

    /**
     * @throws ExceptionInterface
     */
    public function runAction(AbstractAction $action, SymfonyStyle|null $io = null): void
    {
        $this->ensureSetup();
        $this->runActionWithoutSetup($action, $io);
    }

    /**
     * @throws CouldNotDetermineResticVersionException
     */
    public function getResticVersion(): string
    {
        $command = new GetVersion();
        $this->runAction($command);

        if (!$command->getResult()->wasSuccessful()) {
            throw new CouldNotDetermineResticVersionException($command->getResult()->getOutput());
        }

        return $command->getResult()->getVersion();
    }

    public function getItemsToBackup(): array
    {
        if ([] === $this->backupItemsConfig) {
            $this->determineCwdAndItemsToBackup();
        }

        return $this->backupItemsConfig['includeItems'];
    }

    public function getExcludesFromBackup(): array
    {
        if ([] === $this->backupItemsConfig) {
            $this->determineCwdAndItemsToBackup();
        }

        return $this->backupItemsConfig['excludes'];
    }

    public static function determineBinary(string $uname): string|null
    {
        $uname = strtolower($uname);
        $binary = 'restic_'.self::getOS($uname).'_'.self::getArchitecture($uname);

        if (\array_key_exists($binary, self::BINARY_LIST)) {
            return $binary;
        }

        return null;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public static function updateShaSumFile(): void
    {
        $url = str_replace('{version}', self::RESTIC_VERSION, self::DOWNLOAD_URL.'SHA256SUMS');
        $client = HttpClient::create();
        $response = $client->request('GET', $url);
        file_put_contents(self::SHA_SUMS_FILE, $response->getContent());
    }

    private function runActionWithoutSetup(AbstractAction $action, SymfonyStyle|null $io = null): void
    {
        $command = [$this->getResticBinary()];

        if ($io?->getVerbosity() > 0) {
            $command[] = '-vv';
        }

        $command = array_merge($command, $action->getArguments());

        $env = array_merge($this->resticRepoAccessEnv, [
            'RESTIC_CACHE_DIR' => $this->pathForResticDir('cache'),
        ]);

        $process = new Process($command, $this->determineCwd(), $env, null, null);

        $action->run($process, $io);

        // Log all issues as error
        if (!$action->getResult()->wasSuccessful()) {
            $this->logger->error(\sprintf('[Restic action error | %s] %s', basename(str_replace('\\', '/', $action::class)), $action->getResult()->getException()?->getMessage()));
        }
    }

    private function determineCwdAndItemsToBackup(): void
    {
        $backupItems = [];
        $backupExcludes = [];
        $directoriesToBackup = [$this->backupDirectory];

        $this->buildExcludes();

        $symlinks = Finder::create()
            ->in($this->backupDirectory)
            ->exclude($this->excludes)
            ->directories()
            ->depth('< 5') // Let's limit the depth for performance reasons
            ->filter(static fn (\SplFileInfo $file) => $file->isLink())
        ;

        foreach ($symlinks as $symlink) {
            $directoriesToBackup[] = Path::makeAbsolute($symlink->getLinkTarget(), $this->backupDirectory);
        }

        // Symlinks might be outside the project root, we have to find the common base
        // path because restic does not support backing up relative to the project dir.
        $commonBackupPath = Path::getLongestCommonBasePath(...$directoriesToBackup);

        foreach ($directoriesToBackup as $directory) {
            $relativePath = Path::makeRelative($directory, $commonBackupPath);
            if ('' === $relativePath) {
                $relativePath = './';
            }

            $backupItems[] = $relativePath;
        }

        // No need to include the excludes when determining the common backup path. If
        // one of those is outside the path, it will be excluded anyway.
        foreach ($this->excludes as $exclude) {
            $absolutePath = Path::makeAbsolute($exclude, $this->backupDirectory);
            $relativePath = Path::makeRelative($absolutePath, $commonBackupPath);

            // Skip irrelevant excludes outside of our project directory (incorrect configuration)
            if (!str_starts_with($absolutePath, $commonBackupPath)) {
                continue;
            }

            $backupExcludes[] = $relativePath;
        }

        $this->backupItemsConfig = [
            'commonBasePath' => $commonBackupPath,
            'includeItems' => $backupItems,
            'excludes' => $backupExcludes,
        ];
    }

    private function determineCwd(): string
    {
        if ([] === $this->backupItemsConfig) {
            $this->determineCwdAndItemsToBackup();
        }

        return $this->backupItemsConfig['commonBasePath'];
    }

    /**
     * @throws ExceptionInterface
     */
    private function ensureSetup(): void
    {
        (new Filesystem())->mkdir($this->pathForResticDir());
        $this->ensureBinary();
        $this->ensureInit();
    }

    private function pathForResticDir(string ...$paths): string
    {
        return Path::join(
            $this->backupDirectory,
            '/'.$this->resticDirectory,
            ...$paths,
        );
    }

    /**
     * @throws CouldNotDetermineBinaryException|CouldNotDownloadException|CouldNotExtractBinaryException|CouldNotVerifyFileIntegrityException
     */
    private function ensureBinary(): void
    {
        if (file_exists($this->getResticBinary())) {
            return;
        }

        $uname = php_uname();
        $binary = self::determineBinary($uname);
        $binarySourceName = self::BINARY_LIST[$binary] ?? null;

        if (null === $binary || null === $binarySourceName) {
            throw new CouldNotDetermineBinaryException($uname);
        }

        $fs = new Filesystem();

        // Cleanup binary directory first, so we don't keep old Restic releases
        $fs->remove($this->pathForResticDir('binary'));
        $fs->mkdir($this->pathForResticDir('binary'));

        // Determine compressed and uncompressed target names and create empty files
        $binarySourceName = str_replace('{version}', self::RESTIC_VERSION, $binarySourceName);
        $targetCompressed = $this->pathForResticDir('binary', $binarySourceName);
        $targetUncompressed = $this->getResticBinary();
        $fs->dumpFile($targetCompressed, '');
        $fs->dumpFile($targetUncompressed, '');

        $client = HttpClient::create();

        try {
            $response = $client->request('GET', str_replace('{version}', self::RESTIC_VERSION, self::DOWNLOAD_URL).$binarySourceName);

            $fileHandle = fopen($targetCompressed, 'w');

            foreach ($client->stream($response) as $chunk) {
                fwrite($fileHandle, $chunk->getContent());
            }
            fclose($fileHandle);
        } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
            $fs->remove($targetCompressed);
            $fs->remove($targetUncompressed);

            throw new CouldNotDownloadException('Could not download '.$binarySourceName, 0, $e);
        }

        $this->validateFileIntegrity($targetCompressed, $binarySourceName);

        if (str_ends_with($targetCompressed, '.bz2')) {
            self::unCompressBz2($targetCompressed, $targetUncompressed);
        } elseif (str_ends_with($targetCompressed, '.zip')) {
            self::unCompressZip($targetCompressed, $targetUncompressed);
        } else {
            throw new CouldNotExtractBinaryException('Must be either .bz2 or .zip');
        }

        // Ensure file permissions and cleanup
        $fs->chmod($targetUncompressed, 744);
        $fs->remove($targetCompressed);
    }

    private function getResticBinary(): string
    {
        return $this->pathForResticDir('binary', self::RESTIC_BINARY_NAME);
    }

    private function ensureInit(): void
    {
        $action = new InitRepository();
        $this->runActionWithoutSetup($action);

        if ($action->getResult()->wasSuccessful()) {
            return;
        }

        throw new CouldNotInitializeResticRespositoryException('Repository initialization failed: '.$action->getResult()->getOutput());
    }

    private function buildExcludes(): void
    {
        $this->excludes[] = $this->resticDirectory; // Make sure that the directory where we store our stuff is always excluded

        $dotFiles = Finder::create()
            ->in($this->backupDirectory)
            ->exclude($this->excludes)
            ->name('/^\..*/')
            ->ignoreDotFiles(false)
            ->depth('< 5') // Let's limit the depth for performance reasons
        ;

        foreach ($dotFiles as $file) {
            $this->excludes[] = $file->getRelativePathname();
        }
    }

    private static function getOS(string $uname): string
    {
        foreach (self::OS_MATCHER as $regex => $os) {
            preg_match('/'.$regex.'/', $uname, $matches);

            if (isset($matches[0])) {
                return $os;
            }
        }

        return 'unknown';
    }

    private static function getArchitecture(string $uname): string
    {
        foreach (self::ARCH_MATCHER as $regex => $arch) {
            preg_match('/'.$regex.'/', $uname, $matches);

            if (isset($matches[0])) {
                return $arch;
            }
        }

        return 'unknown';
    }

    private static function unCompressBz2(string $compressed, string $targetUncompressed): void
    {
        $targetHandle = fopen($targetUncompressed, 'w');
        $compressedHandle = bzopen($compressed, 'r');

        while (!feof($compressedHandle)) {
            fwrite($targetHandle, bzread($compressedHandle, 4096));
        }
        bzclose($compressedHandle);
        fclose($targetHandle);
    }

    private static function unCompressZip(string $targetCompressed, string $targetUncompressed): void
    {
        $targetHandle = fopen($targetUncompressed, 'w');

        $zip = new \ZipArchive();
        $zip->open($targetCompressed);

        // Restic nests the zipped files
        $filename = $zip->getNameIndex(0);
        $compressedHandle = $zip->getStream($filename);

        while (!feof($compressedHandle)) {
            fwrite($targetHandle, fread($compressedHandle, 4096));
        }

        fclose($targetHandle);
        $zip->close();
    }

    /**
     * @throws CouldNotVerifyFileIntegrityException
     */
    private function validateFileIntegrity(string $targetCompressed, string $binarySourceName): void
    {
        if (!file_exists(self::SHA_SUMS_FILE)) {
            throw new CouldNotVerifyFileIntegrityException('SHASUM file does not exist.');
        }

        if (!file_exists($targetCompressed)) {
            throw new CouldNotVerifyFileIntegrityException(\sprintf('File download for binary source file "%s" was not complete.', $binarySourceName));
        }

        $lines = file(self::SHA_SUMS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $hashes = [];

        foreach ($lines as $line) {
            if (preg_match('/^([a-f0-9]{64})\s+[*]?(.*)$/i', $line, $matches)) {
                $hashes[trim($matches[2])] = strtolower($matches[1]);
            }
        }

        $expectedHash = $hashes[$binarySourceName] ?? null;

        if (null === $expectedHash) {
            throw new CouldNotVerifyFileIntegrityException(\sprintf('SHASUM hash for binary source file "%s" does not exist.', $binarySourceName));
        }

        $actualHash = hash_file('sha256', $targetCompressed);

        if ($expectedHash !== $actualHash) {
            throw new CouldNotVerifyFileIntegrityException(\sprintf('SHASUM hash for binary source file "%s" does not match expected hash.', $binarySourceName));
        }
    }
}
