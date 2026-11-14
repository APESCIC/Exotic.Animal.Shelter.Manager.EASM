<?php

namespace Tests\Unit\Package;

use Easm\Package\PackageException;
use Easm\Package\SafeZipExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeZipExtractorTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easm-zip-'.bin2hex(random_bytes(4));
        mkdir($this->workDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->workDir);
        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    public static function backends(): array
    {
        $backends = [[SafeZipExtractor::BACKEND_PURE]];
        if (class_exists(\ZipArchive::class)) {
            $backends[] = [SafeZipExtractor::BACKEND_ZIPARCHIVE];
        }

        return $backends;
    }

    #[DataProvider('backends')]
    public function test_extracts_safe_archive(string $backend): void
    {
        $zip = $this->workDir.'/safe.zip';
        $this->writeStoredZip($zip, [
            'hello.txt' => 'hello world',
            'nested/dir/file.txt' => 'nested',
        ]);

        $target = $this->workDir.'/out-'.$backend;
        mkdir($target);

        $extractor = new SafeZipExtractor(backend: $backend);
        $extractor->extract($zip, $target);

        $this->assertSame('hello world', file_get_contents($target.'/hello.txt'));
        $this->assertSame('nested', file_get_contents($target.'/nested/dir/file.txt'));
        $this->assertSame($backend, $extractor->resolveBackend());
    }

    #[DataProvider('backends')]
    public function test_rejects_zip_slip(string $backend): void
    {
        $zip = $this->workDir.'/slip.zip';
        $this->writeStoredZip($zip, [
            '../evil.txt' => 'nope',
        ]);

        $target = $this->workDir.'/out-slip-'.$backend;
        mkdir($target);

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('unsafe');
        (new SafeZipExtractor(backend: $backend))->extract($zip, $target);
    }

    #[DataProvider('backends')]
    public function test_rejects_absolute_path(string $backend): void
    {
        $zip = $this->workDir.'/abs.zip';
        $this->writeStoredZip($zip, [
            '/tmp/evil.txt' => 'nope',
        ]);

        $target = $this->workDir.'/out-abs-'.$backend;
        mkdir($target);

        $this->expectException(PackageException::class);
        (new SafeZipExtractor(backend: $backend))->extract($zip, $target);
    }

    #[DataProvider('backends')]
    public function test_rejects_symlink_entry(string $backend): void
    {
        $zip = $this->workDir.'/link.zip';
        $this->writeStoredZip($zip, [
            'link-name' => '/etc/passwd',
        ], symlinkNames: ['link-name']);

        $target = $this->workDir.'/out-link-'.$backend;
        mkdir($target);

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('symbolic link');
        (new SafeZipExtractor(backend: $backend))->extract($zip, $target);
    }

    public function test_pure_php_backend_runs_when_forced_even_if_ziparchive_exists(): void
    {
        $zip = $this->workDir.'/pure.zip';
        $this->writeStoredZip($zip, ['a.txt' => 'pure']);
        $target = $this->workDir.'/out-pure-forced';
        mkdir($target);

        $result = (new SafeZipExtractor(backend: SafeZipExtractor::BACKEND_PURE))
            ->extractChunk($zip, $target, 0, 10);

        $this->assertTrue($result['done']);
        $this->assertSame(SafeZipExtractor::BACKEND_PURE, $result['backend']);
        $this->assertSame('pure', file_get_contents($target.'/a.txt'));
    }

    public function test_chunked_extraction_resumes(): void
    {
        $zip = $this->workDir.'/chunk.zip';
        $this->writeStoredZip($zip, [
            'one.txt' => '1',
            'two.txt' => '2',
            'three.txt' => '3',
        ]);
        $target = $this->workDir.'/out-chunk';
        mkdir($target);

        $extractor = new SafeZipExtractor(backend: SafeZipExtractor::BACKEND_PURE);
        $first = $extractor->extractChunk($zip, $target, 0, 2);
        $this->assertFalse($first['done']);
        $this->assertSame(2, $first['next_index']);
        $this->assertFileExists($target.'/one.txt');
        $this->assertFileExists($target.'/two.txt');
        $this->assertFileDoesNotExist($target.'/three.txt');

        $second = $extractor->extractChunk($zip, $target, $first['next_index'], 2);
        $this->assertTrue($second['done']);
        $this->assertSame('3', file_get_contents($target.'/three.txt'));
    }

    /**
     * @param  array<string, string>  $files
     * @param  list<string>  $symlinkNames
     */
    private function writeStoredZip(string $path, array $files, array $symlinkNames = []): void
    {
        $local = '';
        $central = '';
        $offset = 0;
        $count = 0;

        foreach ($files as $name => $contents) {
            $nameBytes = str_replace('\\', '/', $name);
            $size = strlen($contents);
            $crc = $size === 0 ? 0 : crc32($contents);

            $isLink = in_array($name, $symlinkNames, true);
            $verMade = $isLink ? (3 << 8 | 20) : 20;
            $extAttr = $isLink ? (0xA000 << 16) : 0;

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034B50,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                strlen($nameBytes),
                0
            ).$nameBytes.$contents;

            $centralHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50,
                $verMade,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                strlen($nameBytes),
                0,
                0,
                0,
                0,
                $extAttr,
                $offset
            ).$nameBytes;

            $local .= $localHeader;
            $central .= $centralHeader;
            $offset += strlen($localHeader);
            $count++;
        }

        $cdOffset = strlen($local);
        $eocd = pack(
            'VvvvvVVv',
            0x06054B50,
            0,
            0,
            $count,
            $count,
            strlen($central),
            $cdOffset,
            0
        );

        file_put_contents($path, $local.$central.$eocd);
    }

    private function deleteTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }
}
