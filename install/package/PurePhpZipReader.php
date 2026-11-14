<?php

declare(strict_types=1);

namespace Easm\Package;

/**
 * Minimal ZIP reader (store + deflate) without ext-zip.
 *
 * @phpstan-type ZipEntry array{
 *   name: string,
 *   method: int,
 *   compressed_size: int,
 *   uncompressed_size: int,
 *   local_offset: int,
 *   external_attributes: int,
 *   version_made_by: int,
 *   general_purpose: int,
 *   is_directory: bool
 * }
 */
class PurePhpZipReader
{
    private string $path;

    /** @var resource */
    private $handle;

    private int $size;

    /**
     * @var list<ZipEntry>
     */
    private array $entries = [];

    public function __construct(string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new PackageException('Zip archive is missing or unreadable.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new PackageException('Could not open the zip archive.');
        }

        $this->path = $path;
        $this->handle = $handle;
        $this->size = (int) filesize($path);
        $this->entries = $this->readCentralDirectory();
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * @return list<ZipEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @param  ZipEntry  $entry
     */
    public function readContents(array $entry): string
    {
        if ($entry['is_directory']) {
            return '';
        }

        fseek($this->handle, $entry['local_offset']);
        $local = fread($this->handle, 30);
        if ($local === false || strlen($local) < 30) {
            throw new PackageException('Zip local header is truncated.');
        }

        $header = unpack('Vsig/vver/vflag/vmethod/vtime/vdate/Vcrc/Vcomp/Vuncomp/vnameLen/vextraLen', $local);
        if ($header === false || $header['sig'] !== 0x04034B50) {
            throw new PackageException('Zip local header signature is invalid.');
        }

        $skip = $header['nameLen'] + $header['extraLen'];
        if ($skip > 0) {
            fread($this->handle, $skip);
        }

        $compressed = $entry['compressed_size'] > 0
            ? fread($this->handle, $entry['compressed_size'])
            : '';
        if ($compressed === false || strlen($compressed) !== $entry['compressed_size']) {
            throw new PackageException('Zip compressed data is truncated.');
        }

        if (($entry['general_purpose'] & 0x1) === 0x1) {
            throw new PackageException('Encrypted zip entries are not supported.');
        }

        if ($entry['method'] === 0) {
            $data = $compressed;
        } elseif ($entry['method'] === 8) {
            $data = gzinflate($compressed);
            if ($data === false) {
                throw new PackageException('Zip deflate stream could not be inflated.');
            }
        } else {
            throw new PackageException('Unsupported zip compression method '.$entry['method'].'.');
        }

        if (strlen($data) !== $entry['uncompressed_size']) {
            throw new PackageException('Zip entry size does not match after inflation.');
        }

        return $data;
    }

    /**
     * @return list<ZipEntry>
     */
    private function readCentralDirectory(): array
    {
        $eocdOffset = $this->findEndOfCentralDirectory();
        fseek($this->handle, $eocdOffset);
        $eocd = fread($this->handle, 22);
        if ($eocd === false || strlen($eocd) < 22) {
            throw new PackageException('Zip end-of-central-directory is truncated.');
        }

        $end = unpack('Vsig/vdisk/vcdDisk/vdiskEntries/vtotalEntries/VcdSize/VcdOffset/vcommentLen', $eocd);
        if ($end === false || $end['sig'] !== 0x06054B50) {
            throw new PackageException('Zip end-of-central-directory signature is invalid.');
        }

        fseek($this->handle, $end['cdOffset']);
        $entries = [];
        for ($i = 0; $i < $end['totalEntries']; $i++) {
            $header = fread($this->handle, 46);
            if ($header === false || strlen($header) < 46) {
                throw new PackageException('Zip central directory entry is truncated.');
            }

            $fields = unpack(
                'Vsig/vverMade/vverNeed/vflag/vmethod/vtime/vdate/Vcrc/Vcomp/Vuncomp/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttr/VextAttr/VlocalOffset',
                $header
            );
            if ($fields === false || $fields['sig'] !== 0x02014B50) {
                throw new PackageException('Zip central directory signature is invalid.');
            }

            $name = fread($this->handle, $fields['nameLen']);
            if ($name === false || strlen($name) !== $fields['nameLen']) {
                throw new PackageException('Zip entry name is truncated.');
            }
            $extraSkip = $fields['extraLen'] + $fields['commentLen'];
            if ($extraSkip > 0) {
                fread($this->handle, $extraSkip);
            }

            $isDirectory = str_ends_with($name, '/');
            $entries[] = [
                'name' => $name,
                'method' => $fields['method'],
                'compressed_size' => $fields['comp'],
                'uncompressed_size' => $fields['uncomp'],
                'local_offset' => $fields['localOffset'],
                'external_attributes' => $fields['extAttr'],
                'version_made_by' => $fields['verMade'],
                'general_purpose' => $fields['flag'],
                'is_directory' => $isDirectory,
            ];
        }

        return $entries;
    }

    private function findEndOfCentralDirectory(): int
    {
        $maxBack = min($this->size, 65557);
        fseek($this->handle, -$maxBack, SEEK_END);
        $tail = fread($this->handle, $maxBack);
        if ($tail === false) {
            throw new PackageException('Could not read the zip tail.');
        }

        $pos = strrpos($tail, "PK\x05\x06");
        if ($pos === false) {
            throw new PackageException('Zip end-of-central-directory not found.');
        }

        return $this->size - $maxBack + $pos;
    }
}
