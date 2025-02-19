<?php

require_once __DIR__ . '/../src/TokenGenerator.php';
require_once __DIR__ . '/../src/ApiClient.php';
require_once __DIR__ . '/../src/EncryptionHelper.php';
require_once __DIR__ . '/../src/database_queries.php'; 

// Carica la configurazione
$config = require __DIR__ . '/../config/config.php';


// Crea una connessione al database di partenza
try {
    $db_source = new PDO('mysql:host=' . $config['db']['source']['host'] . ';dbname=' . $config['db']['source']['dbname'], $config['db']['source']['user'], $config['db']['source']['password']);
    $db_source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connessione al database di partenza fallita: " . $e->getMessage());
}

// Crea una connessione al database di arrivo
try {
    $db_destination = new PDO('mysql:host=' . $config['db']['destination']['host'] . ';dbname=' . $config['db']['destination']['dbname'], $config['db']['destination']['user'], $config['db']['destination']['password']);
    $db_destination->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connessione al database di arrivo fallita: " . $e->getMessage());
}

// Genera il token utilizzando la connessione al database di partenza
$tokenGenerator = new TokenGenerator($config['platform']['prefix_token'], $config['encryption']['key'], $config['encryption']['iv'], $db_source);
$token = $tokenGenerator->generateToken();

// Recupera i dati dal database di partenza 
$email = 'nome.cognome@email.it'; // ora è un dato statico ma andrà preso dinamicamente
$contactData = getContactData($db_source, $email, $config['db']['source']['prefix_table'], $config['db']['source']['prefix_field']);

if ($contactData) {
    // Prepara i dati per il ContactController
    $contact = [
        'email' => $contactData['email'],
    ];

    // Invia i dati al ContactController
    $apiClient = new ApiClient($config['api']['contacts_url'], $token);
    $response = $apiClient->sendData($contact);
    echo "Response from ContactController: " . $response . PHP_EOL;

    // Prepara i dati per il ContactExtraController
    $contactExtra = [
        'cb_cognome' => $contactData[$config['db']['source']['prefix_field'] . 'cognome'],
        'cb_codicefiscale' => $contactData[$config['db']['source']['prefix_field'] . 'codicefiscale'],
        'cb_datadinascita' => $contactData[$config['db']['source']['prefix_field'] . 'datadinascita'],
        'cb_luogodinascita' => $contactData[$config['db']['source']['prefix_field'] . 'luogodinascita'],
    ];

    // Invia i dati al ContactExtraController
    $apiClientExtra = new ApiClient($config['api']['contacts_extra_url'], $token);
    $responseExtra = $apiClientExtra->sendData($contactExtra);
    echo "Response from ContactExtraController: " . $responseExtra . PHP_EOL;
} else {
    echo "Nessun dato trovato nel database di partenza.";
}