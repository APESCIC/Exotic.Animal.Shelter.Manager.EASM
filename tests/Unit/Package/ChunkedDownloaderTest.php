<?php

namespace Tests\Unit\Package;

use Easm\Package\ChunkedDownloader;
use Easm\Package\PackageException;
use PHPUnit\Framework\TestCase;

class ChunkedDownloaderTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easm-dl-'.bin2hex(random_bytes(4));
        mkdir($this->workDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->workDir);
        parent::tearDown();
    }

    public function test_rejects_non_https_urls(): void
    {
        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('HTTPS');
        (new ChunkedDownloader)->downloadChunk('http://example.test/file.zip', $this->workDir.'/x.zip');
    }

    public function test_downloads_in_chunks_with_range_responses(): void
    {
        $payload = str_repeat('A', 2500);
        $calls = [];
        $httpGet = function (string $url, array $headers) use ($payload, &$calls): array {
            $calls[] = $headers;
            $range = null;
            foreach ($headers as $header) {
                if (stripos($header, 'Range:') === 0) {
                    $range = trim(substr($header, strlen('Range:')));
                }
            }
            $this->assertNotNull($range);
            $this->assertMatchesRegularExpression('/^bytes=(\d+)-(\d+)$/', (string) $range);
            preg_match('/^bytes=(\d+)-(\d+)$/', (string) $range, $m);
            $start = (int) $m[1];
            $end = (int) $m[2];
            $slice = substr($payload, $start, $end - $start + 1);

            return [
                'status' => 206,
                'headers' => [
                    'HTTP/1.1 206 Partial Content',
                    'Content-Range: bytes '.$start.'-'.($start + strlen($slice) - 1).'/'.strlen($payload),
                ],
                'body' => $slice,
            ];
        };

        $dest = $this->workDir.'/pkg.bin';
        $downloader = new ChunkedDownloader(chunkBytes: 1000, maxBytes: 10_000, httpGet: $httpGet);

        $first = $downloader->downloadChunk('https://example.test/pkg.bin', $dest);
        $this->assertFalse($first['done']);
        $this->assertSame(1000, $first['bytes']);

        $second = $downloader->downloadChunk('https://example.test/pkg.bin', $dest);
        $this->assertFalse($second['done']);
        $this->assertSame(2000, $second['bytes']);

        $third = $downloader->downloadChunk('https://example.test/pkg.bin', $dest);
        $this->assertTrue($third['done']);
        $this->assertSame(2500, $third['bytes']);
        $this->assertSame($payload, file_get_contents($dest));
        $this->assertCount(3, $calls);
    }

    public function test_max_bytes_guard_on_existing_file(): void
    {
        $dest = $this->workDir.'/big.bin';
        file_put_contents($dest, str_repeat('x', 100));

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('size limit');
        (new ChunkedDownloader(chunkBytes: 10, maxBytes: 50))
            ->downloadChunk('https://example.test/x.bin', $dest);
    }
}
