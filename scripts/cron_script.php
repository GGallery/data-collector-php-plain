<?php

require_once __DIR__ . '/../src/TokenGenerator.php';
require_once __DIR__ . '/../src/ApiClient.php';
require_once __DIR__ . '/../src/EncryptionHelper.php';
require_once __DIR__ . '/../src/database_queries.php'; 
require_once __DIR__ . '/../src/SyncStateManager.php';
require_once __DIR__ . '/../src/ErrorLogger.php';

// Carica la configurazione
$config = require __DIR__ . '/../config/config.php';

// Variabili per le configurazioni di sincronizzazione
$batch_size = $config['sync']['batch']['size'];
$max_records_to_process = $config['sync']['batch']['max_per_run']; //numero massimo di record da processare in una singolo batch
$state_file_path = $config['sync']['state_file']['path'];
$lock_file_path = $config['sync']['lock']['file'];
$lock_timeout = $config['sync']['lock']['timeout'];

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
$system_log_url = $config['api']['system_log_url']; // Aggiungi l'URL per il system log
$sync_pointers_url = $config['api']['sync_pointers_url'];


// Ci assicuriamo che la directory data esista
$data_dir = dirname($state_file_path);
if (!file_exists($data_dir)) {
    mkdir($data_dir, 0755, true);
}

// Inizializza il gestore dello stato di sincronizzazione
$syncStateManager = new SyncStateManager($state_file_path, $platform_prefix_token);

// Acquisisce il lock per evitare esecuzioni concorrenti
if (file_exists($lock_file_path)) {
    $lock_time = filemtime($lock_file_path);
    if (time() - $lock_time < $lock_timeout) {
        die("Un altro processo di sincronizzazione è già in esecuzione. Uscita.\n");
    } else {
        echo "Lock file obsoleto rilevato. Rimozione e prosecuzione...\n";
        unlink($lock_file_path);
    }
}

// Crea il file di lock
file_put_contents($lock_file_path, date('Y-m-d H:i:s'));

// Registra la funzione di pulizia per rimuovere il file di lock all'uscita
register_shutdown_function(function() use ($lock_file_path) {
    if (file_exists($lock_file_path)) {
        unlink($lock_file_path);
    }
});


// Carica lo stato corrente della sincronizzazione
$syncState = $syncStateManager->loadState();


// Per debug
// var_dump('Stato sincronizzazione', $syncState); die;


// Inizializza contatori per le statistiche
$totalProcessed = 0;
$successCount = 0;
$errorCount = 0;


// Imposta l'offset dal file di stato o usa il default se non presente
$offset = isset($syncState['offset']) ? $syncState['offset'] : 0;
// Usa il last_id_processed se disponibile
$lastId = isset($syncState['last_id_processed']) ? $syncState['last_id_processed'] : 0;
// Usa le date dal file di stato o dalle configurazioni predefinite
$startDate = isset($syncState['last_update_date']) 
    ? $syncState['last_update_date'] 
    : $config['sync']['time_window']['default_start'];
$endDate = date('Y-m-d'); // Data odierna

echo "Avvio sincronizzazione con: offset=$offset, lastId=$lastId, startDate=$startDate, endDate=$endDate\n";


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


// Inizializza il logger degli errori
$errorLogger = new ErrorLogger($system_log_url, $token, $platform_prefix_token);

// Recupera i dati dal database di partenza
// $offset = 10; // Imposta l'offset iniziale
// $limit = 20; // Imposta il limite per ogni tranche
// $startDate = '2023-01-01'; // Data di inizio per la sincronizzazione delle modifiche
// $endDate = '2023-12-31'; // Data di fine per la sincronizzazione delle modifiche


// $contactDataList = getContactData($db_source, $prefix_table, $prefix_field, $offset, $limit, $startDate, $endDate);

// Ciclo principale di sincronizzazione
do {
    // Recupera i dati utilizzando il "doppio binario"
    $contactDataList = getContactDataIncrementalSync(
        $db_source, 
        $prefix_table, 
        $prefix_field, 
        $lastId, 
        $offset, 
        $batch_size, 
        $startDate, 
        $endDate
    );

    // Debug
    // var_dump(count($contactDataList)); die;
    
    // Se non ci sono più dati, esci dal ciclo
    if (empty($contactDataList)) {
        echo "Nessun altro dato da sincronizzare.\n";
        break;
    }
    
    // Variabili contatore e di riferimento per tracciare il batch
    $batchProcessed = 0;
    $batchSuccess = 0;
    $batchErrors = 0;
    $maxIdProcessed = $lastId;
    $maxUpdateDate = $startDate; // per tenere traccia della data più recente
    
    // nuovo ciclo foreach che include il tracciamento degli id e degli errori
    foreach ($contactDataList as $contactData) { // Per ogni contatto...
        // Tieni traccia dell'ID più alto processato
        if (isset($contactData['id']) && $contactData['id'] > $maxIdProcessed) {
            $maxIdProcessed = $contactData['id'];
        }

        // Tiene traccia della data di aggiornamento più recente
        if (isset($contactData['lastupdatedate']) && !empty($contactData['lastupdatedate']) && $contactData['lastupdatedate'] != '0000-00-00 00:00:00') {
            $contactUpdateDate = $contactData['lastupdatedate'];
            if ($contactUpdateDate > $maxUpdateDate) {
                $maxUpdateDate = $contactUpdateDate;
            }
        }


        // Prepara i dati per il ContactController
        $contact = [
            'email' => $contactData['email'],
        ];
        
        $contactSuccess = false;
        $detailsSuccess = false;
        
        // Invia i dati al ContactController
        try {
            $apiClient = new ApiClient($contacts_url, $token);
            $response = $apiClient->sendData($contact);
            echo "Response from ContactController: " . $response . PHP_EOL;
            $contactSuccess = true;
        } catch (Exception $e) {
            $errorLogger->log(__FILE__, 'sendDataToContactController', $e->getMessage(), $contactData['email']);
            $batchErrors++; //dubug: sendDataToContactController è da cambiare, è un vecchio nome
        }
        
        // Se il contatto è stato creato con successo, procedi con i dettagli
        if ($contactSuccess) {
            // Prepara i dati per il ContactDetailsController
            $contactDetails = [
                'email' => $contactData['email'], 
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
            try {
                $apiClientDetails = new ApiClient($contacts_details_url, $token);
                $responseDetails = $apiClientDetails->sendData($contactDetails);
                echo "Response from ContactDetailsController: " . $responseDetails . PHP_EOL;
                $detailsSuccess = true;
            } catch (Exception $e) {
                $errorLogger->log(__FILE__, 'sendDataToContactDetailsController', $e->getMessage(), $contactData['email']);
                $batchErrors++; //dubug: anche qui sendDataToContactController è da cambiare, è un vecchio nome
            }
        }
        
        // Se entrambe le operazioni hanno avuto successo, incrementa il contatore
        if ($contactSuccess && $detailsSuccess) {
            $batchSuccess++;
        }
        
        $batchProcessed++;
        $totalProcessed++;
        
        // Se abbiamo raggiunto il limite massimo di record, interrompi
        if ($totalProcessed >= $max_records_to_process) {
            break;
        }
    }
    
    // Aggiorna l'offset per la prossima esecuzione
    $offset += $batch_size;
    
    // Aggiorna lo stato di sincronizzazione
    $syncState['last_id_processed'] = $maxIdProcessed;
    $syncState['last_sync_date'] = date('Y-m-d H:i:s');
    $syncState['last_update_date'] = $maxUpdateDate; // Usa la data di aggiornamento più recente trovata nel batch
    $syncState['offset'] = $offset;
    $syncState['processed_records'] = $totalProcessed;
    $syncState['success_count'] = $successCount + $batchSuccess;
    $syncState['error_count'] = $errorCount + $batchErrors;
    
    // Salva lo stato
    $syncStateManager->saveState($syncState);

    // Invia lo stato di sincronizzazione al server per ogni batch
    try {
        // Prepara i dati per l'invio al server
        $syncPointerData = [
            'platform_prefix' => $platform_prefix_token,
            'last_id_processed' => $maxIdProcessed,
            'last_sync_date' => date('Y-m-d H:i:s'),
            'last_update_date' => $maxUpdateDate,
            'processed_records' => $batchProcessed,
            'success_count' => $batchSuccess,
            'error_count' => $batchErrors
        ];
        
        // Invia lo stato al server
        $apiClient = new ApiClient($sync_pointers_url, $token);
        $response = $apiClient->sendData($syncPointerData);
        echo "Stato di sincronizzazione inviato al server: " . $response . PHP_EOL;
    } catch (Exception $e) {
        echo "Errore nell'invio dello stato di sincronizzazione al server: " . $e->getMessage() . PHP_EOL;
        // Non blocchiamo il processo se l'invio dello stato fallisce
    }    
    
    // Aggiorna i contatori totali
    $successCount += $batchSuccess;
    $errorCount += $batchErrors;
    
    echo "Batch completato: processati=$batchProcessed, successo=$batchSuccess, errori=$batchErrors\n";
    
} while ($totalProcessed < $max_records_to_process && !empty($contactDataList));


// Rimuovi il file di lock (anche se già gestito dal register_shutdown_function)
if (file_exists($lock_file_path)) {
    unlink($lock_file_path);
}

// Stampa le statistiche finali
echo "\nSincronizzazione completata!\n";
echo "Totale record processati: $totalProcessed\n";
echo "Successi: $successCount\n";
echo "Errori: $errorCount\n";
echo "ID ultimo record elaborato: {$syncState['last_id_processed']}\n";
echo "Data ultima sincronizzazione: {$syncState['last_sync_date']}\n";


// Invia lo stato di sincronizzazione complessivo al server alla fine del processo
try {
    // Prepara i dati finali per l'invio al server
    $finalSyncPointerData = [
        'platform_prefix' => $platform_prefix_token,
        'last_id_processed' => $syncState['last_id_processed'],
        'last_sync_date' => $syncState['last_sync_date'],
        'last_update_date' => $syncState['last_update_date'],
        'processed_records' => $totalProcessed,
        'success_count' => $successCount,
        'error_count' => $errorCount
    ];
    
    // Invia lo stato finale al server
    $apiClient = new ApiClient($sync_pointers_url, $token);
    $response = $apiClient->sendData($finalSyncPointerData);
    echo "Stato finale di sincronizzazione inviato al server: " . $response . PHP_EOL;
} catch (Exception $e) {
    echo "Errore nell'invio dello stato finale di sincronizzazione al server: " . $e->getMessage() . PHP_EOL;
}