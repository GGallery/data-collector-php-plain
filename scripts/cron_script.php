<?php

require_once __DIR__ . '/../src/TokenGenerator.php';
require_once __DIR__ . '/../src/ApiClient.php';
require_once __DIR__ . '/../src/EncryptionHelper.php';
require_once __DIR__ . '/../src/database_queries.php'; 

// Carica la configurazione
$config = require __DIR__ . '/../config/config.php';

// Variabili per il database di partenza
$source_host = $config['db']['source']['host'];
$source_dbname = $config['db']['source']['dbname'];
$source_user = $config['db']['source']['user'];
$source_password = $config['db']['source']['password'];
$prefix_table = $config['db']['source']['prefix_table'];
$prefix_field = $config['db']['source']['prefix_field'];

// Variabili per il database di arrivo
$destination_host = $config['db']['destination']['host'];
$destination_dbname = $config['db']['destination']['dbname'];
$destination_user = $config['db']['destination']['user'];
$destination_password = $config['db']['destination']['password'];

// Variabili per la piattaforma e la crittografia
$platform_prefix_token = $config['platform']['prefix_token'];
$encryption_key = $config['encryption']['key'];
$encryption_iv = $config['encryption']['iv'];

// Variabili per le API
$contacts_url = $config['api']['contacts_url'];
$contacts_details_url = $config['api']['contacts_details_url'];


// Crea una connessione al database di partenza
try {
    $db_source = new PDO("mysql:host=$source_host;dbname=$source_dbname", $source_user, $source_password);
    $db_source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connessione al database di partenza fallita: " . $e->getMessage());
}

// Crea una connessione al database di arrivo
try {
    $db_destination = new PDO("mysql:host=$destination_host;dbname=$destination_dbname", $destination_user, $destination_password);
    $db_destination->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connessione al database di arrivo fallita: " . $e->getMessage());
}

// Genera il token utilizzando la connessione al database di partenza
$tokenGenerator = new TokenGenerator($platform_prefix_token, $encryption_key, $encryption_iv, $db_source);
$token = $tokenGenerator->generateToken();

// Recupera i dati dal database di partenza 
$email = 'ilaria.bertolotti@mit.gov.it'; // ora è un dato statico ma andrà preso dinamicamente
$contactData = getContactData($db_source, $email, $prefix_table, $prefix_field);

if ($contactData) {
    // Prepara i dati per il ContactController
    $contact = [
        'email' => $contactData['email'],
    ];

    // Invia i dati al ContactController
    $apiClient = new ApiClient($contacts_url, $token);
    $response = $apiClient->sendData($contact);
    echo "Response from ContactController: " . $response . PHP_EOL;

    // Prepara i dati per il ContactDetailsController
    $contactDetails = [
        'cb_cognome' => $contactData[$prefix_field . 'cognome'],
        'cb_codicefiscale' => $contactData[$prefix_field . 'codicefiscale'],
        'cb_datadinascita' => $contactData[$prefix_field . 'datadinascita'],
        'cb_luogodinascita' => $contactData[$prefix_field . 'luogodinascita'],
        'cb_provinciadinascita' => $contactData[$prefix_field . 'provinciadinascita'],
        'cb_indirizzodiresidenza' => $contactData[$prefix_field . 'indirizzodiresidenza'],
        'cb_provdiresidenza' => $contactData[$prefix_field . 'provdiresidenza'],
        'cb_cap' => $contactData[$prefix_field . 'cap'],
        'cb_telefono' => $contactData[$prefix_field . 'telefono'],
        'cb_nome' => $contactData[$prefix_field . 'nome'],
        'cb_citta' => $contactData[$prefix_field . 'citta'],
        'cb_professionedisciplina' => $contactData[$prefix_field . 'professionedisciplina'],
        'cb_ordine' => $contactData[$prefix_field . 'ordine'],
        'cb_numeroiscrizione' => $contactData[$prefix_field . 'numeroiscrizione'],
        'cb_reclutamento' => $contactData[$prefix_field . 'reclutamento'],
        'cb_codicereclutamento' => $contactData[$prefix_field . 'codicereclutamento'],
        'cb_professione' => $contactData[$prefix_field . 'professione'],
        'cb_profiloprofessionale' => $contactData[$prefix_field . 'profiloprofessionale'],
        'cb_settore' => $contactData[$prefix_field . 'settore'],
        'cb_societa' => $contactData[$prefix_field . 'societa'],
    ];

    // Invia i dati al ContactDetailsController
    $apiClientDetails = new ApiClient($contacts_details_url, $token);
    $responseDetails = $apiClientDetails->sendData($contactDetails);
    echo "Response from ContactDetailsController: " . $responseDetails . PHP_EOL;
} else {
    echo "Nessun dato trovato nel database di partenza.";
}