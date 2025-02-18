<?php

require_once __DIR__ . '/../src/TokenGenerator.php';
require_once __DIR__ . '/../src/ApiClient.php';
require_once __DIR__ . '/../src/EncryptionHelper.php';
require_once __DIR__ . '/../src/database_queries.php'; 

// Carica la configurazione
$config = require __DIR__ . '/../config/config.php';

// Crea una connessione al database
try {
    $db = new PDO('mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['dbname'], $config['db']['user'], $config['db']['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connessione fallita: " . $e->getMessage());
}

// Genera il token utilizzando la connessione al database
$tokenGenerator = new TokenGenerator($config['platform']['prefix_token'], $config['encryption']['key'], $config['encryption']['iv'], $db);
$token = $tokenGenerator->generateToken();

// Recupera i dati dal database usando la funzione getContactData definita in database_queries.php
$contactData = getContactData($db, 'example@email.com'); // ora è un dato statico ma immagino debba essere preso dinamicamente

if ($contactData) {
    // Invia i dati all'API
    $apiClient = new ApiClient($config['api']['url'], $token);
    $response = $apiClient->sendData($contactData);

    echo $response;
} else {
    echo "Nessun dato trovato nel database.";
}