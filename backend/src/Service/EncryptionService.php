<?php

namespace App\Service;

class EncryptionService
{

    private string $key;

    public function __construct(string $key)
    {
        $this->key = base64_decode($key);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);

        $ciphertext = openssl_encrypt(
            $plaintext,
            'AES-256-GCM',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        $payload = [
            'version' => 1,
            'iv' => base64_encode($iv),
            'ciphertext' => base64_encode($ciphertext),
            'tag' => base64_encode($tag),
        ];

        return base64_encode(json_encode($payload));
    }

    public function decrypt(string $ciphertext): string
    {
        $payload = json_decode(base64_decode($ciphertext), true);
        $iv = base64_decode($payload['iv']);
        $ciphertext = base64_decode($payload['ciphertext']);
        $tag = base64_decode($payload['tag']);

        return openssl_decrypt(
            $ciphertext,
            'AES-256-GCM',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
    }

}
