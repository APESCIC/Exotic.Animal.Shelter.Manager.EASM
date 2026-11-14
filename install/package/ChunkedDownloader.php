<?php

declare(strict_types=1);

namespace Easm\Package;

/**
 * HTTPS file download in bounded chunks so a browser AJAX step can resume.
 *
 * Uses HTTP Range when the server supports it; otherwise appends from the
 * current local length by reading and discarding the already-fetched prefix.
 */
class ChunkedDownloader
{
    private int $chunkBytes;

    private int $maxBytes;

    private int $timeoutSeconds;

    /** @var null|callable(string, array<int, string>): array{status: int, headers: list<string>, body: string} */
    private $httpGet;

    /**
     * @param  null|callable(string, array<int, string>): array{status: int, headers: list<string>, body: string}  $httpGet
     */
    public function __construct(
        int $chunkBytes = 1_048_576,
        int $maxBytes = 104_857_600,
        int $timeoutSeconds = 30,
        ?callable $httpGet = null
    ) {
        if ($chunkBytes < 1) {
            throw new PackageException('Chunk size must be at least 1 byte.');
        }
        if ($maxBytes < 1) {
            throw new PackageException('Maximum download size must be at least 1 byte.');
        }

        $this->chunkBytes = $chunkBytes;
        $this->maxBytes = $maxBytes;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->httpGet = $httpGet;
    }

    /**
     * Fetch the next chunk into $destPath (created or appended).
     *
     * @return array{done: bool, bytes: int, path: string, total: int|null}
     */
    public function downloadChunk(string $url, string $destPath, ?int $offset = null): array
    {
        $this->assertHttpsUrl($url);

        $dir = dirname($destPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new PackageException('Could not create download directory.');
        }

        $current = is_file($destPath) ? (int) filesize($destPath) : 0;
        $start = $offset === null ? $current : $offset;
        if ($start < 0) {
            throw new PackageException('Download offset cannot be negative.');
        }
        if ($start > $this->maxBytes) {
            throw new PackageException('Download already exceeds the size limit.');
        }
        if ($start !== $current) {
            if ($start < $current) {
                $handle = fopen($destPath, 'cb');
                if ($handle === false) {
                    throw new PackageException('Could not open the download file for truncation.');
                }
                ftruncate($handle, $start);
                fclose($handle);
                clearstatcache(true, $destPath);
            } else {
                throw new PackageException('Download offset is past the local file length.');
            }
        }

        $end = min($start + $this->chunkBytes - 1, $this->maxBytes - 1);
        $requestHeaders = [
            'User-Agent: EASM-PackageDownloader/1.0',
            'Accept: */*',
            'Range: bytes='.$start.'-'.$end,
        ];

        $response = $this->fetch($url, $requestHeaders);
        $status = $response['status'];
        $body = $response['body'];
        $total = $this->totalFromHeaders($response['headers'], $start, strlen($body));

        if ($status === 200 && $start > 0) {
            if (strlen($body) < $start) {
                throw new PackageException('Download shorter than the requested offset.');
            }
            $body = substr($body, $start, $this->chunkBytes);
            $status = 206;
        }

        if ($status !== 200 && $status !== 206) {
            throw new PackageException('Unexpected HTTP status '.$status.' while downloading.');
        }

        if ($start + strlen($body) > $this->maxBytes) {
            throw new PackageException('Download would exceed the size limit.');
        }

        $mode = $start === 0 ? 'wb' : 'ab';
        $out = fopen($destPath, $mode);
        if ($out === false) {
            throw new PackageException('Could not write the download file.');
        }
        $written = fwrite($out, $body);
        fclose($out);
        if ($written !== strlen($body)) {
            throw new PackageException('Incomplete write while saving the download.');
        }

        $bytes = (int) filesize($destPath);
        $done = $status === 200
            || ($total !== null && $bytes >= $total)
            || strlen($body) === 0
            || strlen($body) < $this->chunkBytes;

        if ($bytes > $this->maxBytes) {
            @unlink($destPath);
            throw new PackageException('Download exceeded the size limit.');
        }

        return [
            'done' => $done,
            'bytes' => $bytes,
            'path' => $destPath,
            'total' => $total,
        ];
    }

    /**
     * @param  list<string>  $headers
     * @return array{status: int, headers: list<string>, body: string}
     */
    private function fetch(string $url, array $headers): array
    {
        if ($this->httpGet !== null) {
            return ($this->httpGet)($url, $headers);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new PackageException('HTTPS download failed.');
        }

        /** @var list<string> $responseHeaders */
        $responseHeaders = $http_response_header ?? [];

        return [
            'status' => $this->responseStatus($responseHeaders),
            'headers' => $responseHeaders,
            'body' => $body,
        ];
    }

    private function assertHttpsUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new PackageException('Only HTTPS URLs are allowed for package downloads.');
        }
    }

    /**
     * @param  list<string>  $headers
     */
    private function responseStatus(array $headers): int
    {
        if ($headers === []) {
            return 0;
        }
        if (! preg_match('/\s(\d{3})\s/', $headers[0], $m)) {
            return 0;
        }

        return (int) $m[1];
    }

    /**
     * @param  list<string>  $headers
     */
    private function totalFromHeaders(array $headers, int $start, int $bodyLength): ?int
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Range:') === 0) {
                if (preg_match('/bytes\s+\d+-\d+\/(\d+|\*)/i', $header, $m)) {
                    return $m[1] === '*' ? null : (int) $m[1];
                }
            }
            if (stripos($header, 'Content-Length:') === 0 && $start === 0) {
                return (int) trim(substr($header, strlen('Content-Length:')));
            }
        }

        return $start === 0 ? $bodyLength : null;
    }
}
