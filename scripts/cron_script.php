<?php

require_once __DIR__ . '/../src/TokenGenerator.php';
require_once __DIR__ . '/../src/ApiClient.php';
require_once __DIR__ . '/../src/EncryptionHelper.php';

// Carica la configurazione
$config = require __DIR__ . '/../config/config.php';

// Debug: stampa il prefisso del token
// echo "Prefix Token: " . $config['platform']['prefix_token'] . PHP_EOL;

// Crea una connessione al database
try {
    $db = new PDO('mysql:host=localhost;dbname=my_data_collector', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Genera il token utilizzando la connessione al database
$tokenGenerator = new TokenGenerator($config['platform']['prefix_token'], $config['encryption']['key'], $config['encryption']['iv'], $db);
$token = $tokenGenerator->generateToken();

// Prepara i dati da inviare
$data = [
    'name' => 'Alice Wonderland',
    'email' => 'alice.wonderland@example.com',
    'phone' => '9876543210',
    'message' => 'Segui il bianconiglio, Alice'
];

// Invia i dati all'API
$apiClient = new ApiClient($config['api']['url'], $token);
$response = $apiClient->sendData($data);

echo $response;