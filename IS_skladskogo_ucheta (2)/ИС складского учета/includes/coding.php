<?php
/**
 * Шифрование чувствительной информации в БД (персональные данные, контактная информация).
 * AES-128-CBC, ключ из config (ENCRYPTION_KEY).
 */

function encryptSensitive($text) {
    if ($text === null || $text === '') {
        return $text;
    }
    $key = ENCRYPTION_KEY;
    $keyHash = md5($key);
    $keyBytes = hex2bin($keyHash);
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt(
        $text,
        'aes-128-cbc',
        $keyBytes,
        OPENSSL_RAW_DATA,
        $iv
    );
    if ($encrypted === false) {
        return $text;
    }
    return base64_encode($iv . $encrypted);
}

function decryptSensitive($text) {
    if ($text === null || $text === '') {
        return $text;
    }
    $data = base64_decode($text, true);
    if ($data === false || strlen($data) < 17) {
        return $text; // не зашифровано или битые данные — возвращаем как есть
    }
    $key = ENCRYPTION_KEY;
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    $keyHash = md5($key);
    $keyBytes = hex2bin($keyHash);
    $decrypted = openssl_decrypt(
        $encrypted,
        'aes-128-cbc',
        $keyBytes,
        OPENSSL_RAW_DATA,
        $iv
    );
    return $decrypted !== false ? $decrypted : $text;
}
