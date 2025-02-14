<?php

class EncryptionHelper
{
    public static function encryptDecrypt($string, $secret_key, $secret_iv, $action = 'encrypt')
    {
        $output = false;
        $encrypt_method = "AES-256-CBC";
        // Genera una chiave hash
        $key = hash('sha256', $secret_key);

        // encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        if ($action == 'encrypt') {
            // Cripta la stringa
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } else if ($action == 'decrypt') {
            // Decripta la stringa
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }
        return $output;
    }
}

