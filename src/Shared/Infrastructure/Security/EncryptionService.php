<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

/**
 * Encrypts and decrypts sensitive data using Sodium (libsodium).
 *
 * Uses symmetric encryption (same key for encrypt/decrypt).
 * The encryption key must be stored securely in environment variables.
 */
final class EncryptionService
{
    private string $encryptionKey;

    public function __construct(string $encryptionKey)
    {
        // Decode the base64-encoded key from env
        $key = base64_decode($encryptionKey, true);

        if (false === $key || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen($key)) {
            throw new \RuntimeException('Invalid encryption key. Must be a valid base64-encoded 32-byte key.');
        }

        $this->encryptionKey = $key;
    }

    /**
     * Encrypt a string value.
     *
     * @throws \RuntimeException if encryption fails
     */
    public function encrypt(string $plaintext): string
    {
        // Generate a random nonce (number used once)
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Encrypt the data
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->encryptionKey);

        // Combine nonce + ciphertext and encode as base64
        return base64_encode($nonce.$ciphertext);
    }

    /**
     * Decrypt an encrypted string value.
     *
     * @throws \RuntimeException if decryption fails
     */
    public function decrypt(string $encrypted): string
    {
        // Decode from base64
        $decoded = base64_decode($encrypted, true);

        if (false === $decoded) {
            throw new \RuntimeException('Invalid encrypted data: not valid base64');
        }

        // Extract nonce and ciphertext
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        // Decrypt
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->encryptionKey);

        if (false === $plaintext) {
            throw new \RuntimeException('Decryption failed. Data may be corrupted or key is incorrect.');
        }

        return $plaintext;
    }

    /**
     * Generate a new random encryption key (base64-encoded).
     * Use this once to generate a key for .env.local.
     */
    public static function generateKey(): string
    {
        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

        return base64_encode($key);
    }
}
