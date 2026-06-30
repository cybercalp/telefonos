<?php
use PHPUnit\Framework\TestCase;

class CryptTest extends TestCase
{
    private string $key;
    private string $uuid;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../lib/crypt.php';
        $this->key = str_repeat('k', 32); // 32-byte key for AES-256
        $this->uuid = '550e8400-e29b-41d4-a716-446655440000';
    }

    public function testEncryptDecryptCbcRoundtrip(): void
    {
        $plaintext = 'test-secret-data';
        $encrypted = encryptSecret($plaintext, $this->key);
        $decrypted = decryptSecret($encrypted, $this->key);

        $this->assertSame($plaintext, $decrypted);
    }

    public function testCbcWithWrongKeyReturnsFalse(): void
    {
        $plaintext = 'test-secret-data';
        $encrypted = encryptSecret($plaintext, $this->key);
        $wrongKey = str_repeat('w', 32);

        $result = @decryptSecret($encrypted, $wrongKey);

        // AES-256-CBC has no authentication; openssl_decrypt returns false
        // when padding is invalid, but ~0.4% of random keys produce
        // coincidentally valid padding. Either way, the result MUST NOT
        // match the original plaintext.
        $this->assertNotSame(
            $plaintext,
            $result,
            'Decrypting with wrong key must not return the original plaintext'
        );
    }

    public function testCbcWithTamperedCiphertextReturnsFalse(): void
    {
        $plaintext = 'test-secret-data';
        $encrypted = encryptSecret($plaintext, $this->key);

        // Tamper by replacing a middle character with another valid base64 char
        $pos = (int) (strlen($encrypted) / 2);
        $tampered = substr($encrypted, 0, $pos) . 'B' . substr($encrypted, $pos + 1);

        $result = @decryptSecret($tampered, $this->key);

        // Tampering the ciphertext should make decryption fail — either
        // return false or produce garbage that doesn't match the original.
        $this->assertNotSame(
            $plaintext,
            $result,
            'Decrypting tampered ciphertext must not return original plaintext'
        );
    }

    public function testEncryptDecryptGcmRoundtrip(): void
    {
        $plaintext = 'test-gcm-secret';

        ob_start();
        $encrypted = encryptSecretGCM($plaintext, $this->uuid);
        ob_end_clean();

        $decrypted = decryptSecretGCM($encrypted, $this->uuid);

        $this->assertSame($plaintext, $decrypted);
    }

    public function testGcmWithWrongUuidThrowsException(): void
    {
        $plaintext = 'test-gcm-secret';

        ob_start();
        $encrypted = encryptSecretGCM($plaintext, $this->uuid);
        ob_end_clean();

        $wrongUuid = '660e8400-e29b-41d4-a716-446655440001';

        $this->expectException(\Exception::class);
        decryptSecretGCM($encrypted, $wrongUuid);
    }

    public function testGcmWithTamperedDataThrowsException(): void
    {
        $plaintext = 'test-gcm-secret';

        ob_start();
        $encrypted = encryptSecretGCM($plaintext, $this->uuid);
        ob_end_clean();

        // Tamper with the base64 string
        $pos = (int) (strlen($encrypted) / 2);
        $tampered = substr($encrypted, 0, $pos) . 'X' . substr($encrypted, $pos + 1);

        $this->expectException(\Exception::class);
        decryptSecretGCM($tampered, $this->uuid);
    }

    public function testGcmWithShortInputThrowsException(): void
    {
        // base64 of a very short string decodes to < 28 bytes
        $shortBase64 = base64_encode('x');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Entrada cifrada no válida');
        decryptSecretGCM($shortBase64, $this->uuid);
    }

    public function testEncryptSecretProducesDifferentOutputsForSameInput(): void
    {
        $plaintext = 'same-input';

        $out1 = encryptSecret($plaintext, $this->key);
        $out2 = encryptSecret($plaintext, $this->key);

        $this->assertNotSame(
            $out1,
            $out2,
            'Each encryption should produce different output due to random IV'
        );
    }

    public function testEncryptSecretGcmProducesDifferentOutputsForSameInput(): void
    {
        $plaintext = 'same-input';

        ob_start();
        $out1 = encryptSecretGCM($plaintext, $this->uuid);
        ob_end_clean();

        ob_start();
        $out2 = encryptSecretGCM($plaintext, $this->uuid);
        ob_end_clean();

        $this->assertNotSame(
            $out1,
            $out2,
            'Each GCM encryption should produce different output due to random IV'
        );
    }

    public function testEncryptSecretGcmOutputFitsPagerField(): void
    {
        $plaintext = 'test-gcm-secret';

        ob_start();
        $result = encryptSecretGCM($plaintext, $this->uuid);
        ob_end_clean();

        $this->assertLessThanOrEqual(
            1024,
            strlen($result),
            'GCM output must fit within AD pager field limit (1024 bytes)'
        );
    }
}
