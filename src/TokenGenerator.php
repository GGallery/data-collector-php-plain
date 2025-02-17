<?php


require_once __DIR__ . '/EncryptionHelper.php';

class TokenGenerator
{
    private $prefix_token;
    private $encryption_key;
    private $encryption_iv;
    private $db;

    // Inizializza il prefisso del token, la chiave di crittografia e l'IV
    public function __construct($prefix_token, $encryption_key, $encryption_iv, $db)
    {
        $this->prefix_token = $prefix_token;
        $this->encryption_key = $encryption_key;
        $this->encryption_iv = $encryption_iv;
        $this->db = $db;
    }
    
    // Genera un token criptato
    public function generateToken()
    {
        // Ottiene il timestamp unix corrente
        $current_time = time();
        // var_dump($current_time);

        $combined_token = $this->prefix_token . $current_time;

        // Cripta il token combinato
        $encrypted_token = EncryptionHelper::encryptDecrypt($combined_token, $this->encryption_key, $this->encryption_iv, 'encrypt');

        return $encrypted_token;
    }
}