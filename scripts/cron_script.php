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
$system_log_url = $config['api']['system_log_url']; 

// Variabili per il batch
$batch_size = $config['batch']['size'];
$start_date = $config['batch']['start_date'];
$end_date = $config['batch']['end_date'];


// Funzione per registrare gli errori nel system log
function logError($file, $function, $message, $email, $platform_name, $system_log_url, $token, $errorType = 'client') {
    static $errors = [];

    // Aggiunge l'errore all'array
    $errors[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $errorType,
        'context' => [
            'file' => $file,
            'function' => $function,
            'email' => $email
        ],
        'error' => [
            'message' => $message,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ]
    ];

    // Prepara i dati per l'invio
    $logData = [
        'file' => $file,
        'function_name' => $function,
        'message' => json_encode(['errors' => $errors], JSON_PRETTY_PRINT),
        'email' => $email,
        'platform_name' => $platform_name,
    ];

    $apiClient = new ApiClient($system_log_url, $token);
    $apiClient->sendData($logData);
}


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


// Inizializza l'offset e ottiene il primo batch di dati
$offset = 0;
$totalProcessed = 0;
$maxRecordsToProcess = 100; // Limite di sicurezza ciclo while

$contactDataList = getContactData($db_source, $prefix_table, $prefix_field, $offset, $batch_size, $start_date, $end_date);

// Ciclo per processare i dati in batch (con limite di sicurezza)
while (!empty($contactDataList) && $totalProcessed < $maxRecordsToProcess) {
    // Array per i contatti e i loro dettagli
    $contactsBatch = [];
    $contactDetailsBatch = [];
    
    // Prepara i dati per batch
    foreach ($contactDataList as $contactData) {
        // Contatto base
        $contactsBatch[] = [
            'email' => $contactData['email']
        ];
        
        // Dettagli del contatto, include email per l'associazione
        $contactDetailsBatch[] = [
            'email' => $contactData['email'], // Email per collegare al contatto base
            'cb_cognome' => $contactData[$prefix_field . 'cognome'] ?? '',
            'cb_codicefiscale' => $contactData[$prefix_field . 'codicefiscale'] ?? '',
            'cb_datadinascita' => $contactData[$prefix_field . 'datadinascita'] ?? null,
            'cb_luogodinascita' => $contactData[$prefix_field . 'luogodinascita'] ?? '',
            'cb_provinciadinascita' => $contactData[$prefix_field . 'provinciadinascita'] ?? '',
            'cb_indirizzodiresidenza' => $contactData[$prefix_field . 'indirizzodiresidenza'] ?? '',
            'cb_provdiresidenza' => $contactData[$prefix_field . 'provdiresidenza'] ?? '',
            'cb_cap' => $contactData[$prefix_field . 'cap'] ?? '',
            'cb_telefono' => $contactData[$prefix_field . 'telefono'] ?? '',
            'cb_nome' => $contactData[$prefix_field . 'nome'] ?? '',
            'cb_citta' => $contactData[$prefix_field . 'citta'] ?? '',
            'cb_professionedisciplina' => $contactData[$prefix_field . 'professionedisciplina'] ?? '',
            'cb_ordine' => $contactData[$prefix_field . 'ordine'] ?? '',
            'cb_numeroiscrizione' => $contactData[$prefix_field . 'numeroiscrizione'] ?? '',
            'cb_reclutamento' => $contactData[$prefix_field . 'reclutamento'] ?? '',
            'cb_codicereclutamento' => $contactData[$prefix_field . 'codicereclutamento'] ?? '',
            'cb_professione' => $contactData[$prefix_field . 'professione'] ?? '',
            'cb_profiloprofessionale' => $contactData[$prefix_field . 'profiloprofessionale'] ?? '',
            'cb_settore' => $contactData[$prefix_field . 'settore'] ?? '',
            'cb_societa' => $contactData[$prefix_field . 'societa'] ?? ''
        ];
    }
    
    // Invia il batch di contatti
    try {
        $apiClient = new ApiClient($contacts_url, $token);
        $response = $apiClient->sendData(['contacts' => $contactsBatch]);
        echo "Response from ContactController (batch): " . $response . PHP_EOL;
    } catch (Exception $e) {
        echo "Errore durante l'invio del batch di contatti: " . $e->getMessage() . PHP_EOL;
        logError(__FILE__, 'sendContactsBatch', $e->getMessage(), 'batch-operation', $platform_prefix_token, $system_log_url, $token);
    }
    
    // Invia il batch di dettagli
    try {
        $apiClientDetails = new ApiClient($contacts_details_url, $token);
        $responseDetails = $apiClientDetails->sendData(['contacts_details' => $contactDetailsBatch]);
        echo "Response from ContactDetailsController (batch): " . $responseDetails . PHP_EOL;
    } catch (Exception $e) {
        echo "Errore durante l'invio del batch di dettagli: " . $e->getMessage() . PHP_EOL;
        logError(__FILE__, 'sendContactDetailsBatch', $e->getMessage(), 'batch-operation', $platform_prefix_token, $system_log_url, $token);
    }
    
    // Incrementa contatori e prepara il prossimo batch
    $totalProcessed += count($contactDataList);
    $offset += $batch_size;
    
    // Ottieni il prossimo batch
    $contactDataList = getContactData($db_source, $prefix_table, $prefix_field, $offset, $batch_size, $start_date, $end_date);
    
    // Piccola pausa tra i batch
    sleep(1);
}

echo "Processo completato. Totale record elaborati: $totalProcessed\n";