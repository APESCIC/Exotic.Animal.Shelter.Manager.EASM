<?php

declare(strict_types=1);

namespace Easm\Package;

/**
 * SHA-256 and minisign verification using only PHP libsodium (no shell).
 */
class ReleaseVerifier
{
    private string $publicKeyPath;

    public function __construct(string $publicKeyPath)
    {
        $this->publicKeyPath = $publicKeyPath;
    }

    public function verifySha256(string $filePath, string $expectedHex): void
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new PackageException('Package file is missing or unreadable.');
        }

        $expected = strtolower(trim($expectedHex));
        if (! preg_match('/^[a-f0-9]{64}$/', $expected)) {
            throw new PackageException('SHA-256 checksum must be 64 hexadecimal characters.');
        }

        $actual = hash_file('sha256', $filePath);
        if (! is_string($actual) || ! hash_equals($expected, $actual)) {
            throw new PackageException('SHA-256 checksum does not match.');
        }
    }

    /**
     * Parse a SHA256SUMS line ("<hex>  <filename>" or "<hex> *<filename>").
     */
    public function expectedSha256FromSums(string $sumsContents, string $basename): string
    {
        foreach (preg_split('/\R/', $sumsContents) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! preg_match('/^([a-fA-F0-9]{64})\s+\*?(\S+)\s*$/', $line, $m)) {
                continue;
            }
            if (basename($m[2]) === $basename) {
                return strtolower($m[1]);
            }
        }

        throw new PackageException('No SHA-256 entry found for '.$basename.'.');
    }

    public function verifyMinisign(string $filePath, string $signaturePath): void
    {
        if (! extension_loaded('sodium')) {
            throw new PackageException('The sodium extension is required to verify minisign signatures.');
        }
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new PackageException('Package file is missing or unreadable.');
        }
        if (! is_file($signaturePath) || ! is_readable($signaturePath)) {
            throw new PackageException('Minisign signature file is missing or unreadable.');
        }
        if (! is_file($this->publicKeyPath) || ! is_readable($this->publicKeyPath)) {
            throw new PackageException('Minisign public key is missing or unreadable.');
        }

        $public = $this->parsePublicKey((string) file_get_contents($this->publicKeyPath));
        $signature = $this->parseSignature((string) file_get_contents($signaturePath));

        if (! hash_equals($public['key_id'], $signature['key_id'])) {
            throw new PackageException('Minisign key id does not match the public key.');
        }

        $message = (string) file_get_contents($filePath);
        if ($signature['prehashed']) {
            $message = sodium_crypto_generichash($message, '', 64);
        }

        $ok = sodium_crypto_sign_verify_detached(
            $signature['signature'],
            $message,
            $public['public_key']
        );
        $globalOk = sodium_crypto_sign_verify_detached(
            $signature['global_signature'],
            $signature['signature'].$signature['trusted_comment'],
            $public['public_key']
        );

        if (! $ok || ! $globalOk) {
            throw new PackageException('Minisign signature verification failed.');
        }
    }

    public function verify(string $filePath, string $expectedSha256Hex, string $signaturePath): void
    {
        $this->verifySha256($filePath, $expectedSha256Hex);
        $this->verifyMinisign($filePath, $signaturePath);
    }

    /**
     * @return array{key_id: string, public_key: string}
     */
    private function parsePublicKey(string $contents): array
    {
        $lines = preg_split('/\R/', trim($contents)) ?: [];
        if (count($lines) < 2) {
            throw new PackageException('Minisign public key file is incomplete.');
        }

        $decoded = base64_decode(trim($lines[1]), true);
        if ($decoded === false || strlen($decoded) !== 42) {
            throw new PackageException('Minisign public key encoding is invalid.');
        }
        if (substr($decoded, 0, 2) !== 'Ed') {
            throw new PackageException('Unsupported minisign public key algorithm.');
        }

        return [
            'key_id' => substr($decoded, 2, 8),
            'public_key' => substr($decoded, 10, 32),
        ];
    }

    /**
     * @return array{prehashed: bool, key_id: string, signature: string, trusted_comment: string, global_signature: string}
     */
    private function parseSignature(string $contents): array
    {
        $lines = preg_split('/\R/', trim($contents)) ?: [];
        if (count($lines) < 4) {
            throw new PackageException('Minisign signature file is incomplete.');
        }

        $decoded = base64_decode(trim($lines[1]), true);
        if ($decoded === false || strlen($decoded) < 74) {
            throw new PackageException('Minisign signature encoding is invalid.');
        }

        $alg = substr($decoded, 0, 2);
        if ($alg !== 'Ed' && $alg !== 'ED') {
            throw new PackageException('Unsupported minisign signature algorithm.');
        }

        if (! preg_match('/^trusted comment:\s?(.*)$/i', $lines[2], $m)) {
            throw new PackageException('Minisign trusted comment line is missing.');
        }

        $global = base64_decode(trim($lines[3]), true);
        if ($global === false || strlen($global) !== 64) {
            throw new PackageException('Minisign global signature encoding is invalid.');
        }

        $signature = substr($decoded, 10);
        if (strlen($signature) !== 64) {
            throw new PackageException('Minisign signature length is invalid.');
        }

        return [
            'prehashed' => $alg === 'ED',
            'key_id' => substr($decoded, 2, 8),
            'signature' => $signature,
            'trusted_comment' => $m[1],
            'global_signature' => $global,
        ];
    }
}
