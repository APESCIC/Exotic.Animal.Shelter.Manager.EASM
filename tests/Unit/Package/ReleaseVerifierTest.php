<?php

namespace Tests\Unit\Package;

use Easm\Package\PackageException;
use Easm\Package\ReleaseVerifier;
use PHPUnit\Framework\TestCase;

class ReleaseVerifierTest extends TestCase
{
    private string $workDir;

    private string $publicKeyPath;

    private string $secretKey;

    private string $keyId;

    private string $publicKeyBytes;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('sodium extension required');
        }

        $this->workDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easm-verify-'.bin2hex(random_bytes(4));
        mkdir($this->workDir, 0755, true);

        $keypair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($keypair);
        $this->publicKeyBytes = sodium_crypto_sign_publickey($keypair);
        $this->keyId = random_bytes(8);

        $this->publicKeyPath = $this->workDir.'/easm-release.minisign.pub';
        $encoded = base64_encode('Ed'.$this->keyId.$this->publicKeyBytes);
        file_put_contents(
            $this->publicKeyPath,
            "untrusted comment: minisign public key test\n".$encoded."\n"
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->workDir);
        parent::tearDown();
    }

    public function test_accepts_matching_sha256_and_minisign(): void
    {
        $file = $this->workDir.'/easm-0.9.0.zip';
        file_put_contents($file, 'release-bytes');
        $sig = $this->signFile($file, prehashed: true);

        $verifier = new ReleaseVerifier($this->publicKeyPath);
        $verifier->verify($file, hash_file('sha256', $file), $sig);
        $this->addToAssertionCount(1);
    }

    public function test_rejects_bad_checksum(): void
    {
        $file = $this->workDir.'/bad-sum.zip';
        file_put_contents($file, 'payload');
        $sig = $this->signFile($file, prehashed: true);

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('SHA-256');
        (new ReleaseVerifier($this->publicKeyPath))->verify(
            $file,
            str_repeat('0', 64),
            $sig
        );
    }

    public function test_rejects_bad_signature(): void
    {
        $file = $this->workDir.'/bad-sig.zip';
        file_put_contents($file, 'payload');
        $sigPath = $this->signFile($file, prehashed: true);
        $tampered = $this->workDir.'/tampered.zip';
        file_put_contents($tampered, 'payload!');

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('Minisign signature');
        (new ReleaseVerifier($this->publicKeyPath))->verify(
            $tampered,
            hash_file('sha256', $tampered),
            $sigPath
        );
    }

    public function test_parses_sha256sums_file(): void
    {
        $verifier = new ReleaseVerifier($this->publicKeyPath);
        $hex = $verifier->expectedSha256FromSums(
            "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa  easm-1.0.0.zip\n",
            'easm-1.0.0.zip'
        );
        $this->assertSame(str_repeat('a', 64), $hex);
    }

    public function test_legacy_non_prehashed_signature(): void
    {
        $file = $this->workDir.'/legacy.zip';
        file_put_contents($file, 'legacy-payload');
        $sig = $this->signFile($file, prehashed: false);

        (new ReleaseVerifier($this->publicKeyPath))->verifyMinisign($file, $sig);
        $this->addToAssertionCount(1);
    }

    private function signFile(string $filePath, bool $prehashed): string
    {
        $contents = (string) file_get_contents($filePath);
        $message = $prehashed ? sodium_crypto_generichash($contents, '', 64) : $contents;
        $signature = sodium_crypto_sign_detached($message, $this->secretKey);
        $trusted = 'timestamp:'.time().'\tfile:'.basename($filePath);
        $global = sodium_crypto_sign_detached($signature.$trusted, $this->secretKey);
        $alg = $prehashed ? 'ED' : 'Ed';

        $sigPath = $filePath.'.minisig';
        $body = "untrusted comment: signature from test secret key\n"
            .base64_encode($alg.$this->keyId.$signature)."\n"
            .'trusted comment: '.$trusted."\n"
            .base64_encode($global)."\n";
        file_put_contents($sigPath, $body);

        return $sigPath;
    }
}
