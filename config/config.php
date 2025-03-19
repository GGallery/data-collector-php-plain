<?php

return [
    'db' => [
        'source' => [
            'host' => 'localhost',
            'dbname' => 'prima_new', 
            'user' => 'root',
            'password' => '',
            'prefix_table' => 'ms_', // Prefisso delle tabelle
            'prefix_field' => 'cb_', // Prefisso table fields
        ],
        'destination' => [
            'host' => 'localhost',
            'dbname' => 'my_data_collector', 
            'user' => 'root',
            'password' => '',
        ],
    ],
    'platform' => [
        'name' => 'Prima',
        'prefix_token' => '', 
    ],
    'encryption' => [
        'key' => '', // Chiave di crittografia
        'iv' => '', // IV di crittografia
    ],
    'api' => [
        'contacts_url' => 'http://localhost:8000/api/contacts',
        'contacts_details_url' => 'http://localhost:8000/api/contacts_details',
        'system_log_url' => 'http://localhost:8000/api/system_log' 
    ],

    // Configurazioni di sincronizzazione
    'sync' => [
        'batch' => [
            'size' => 20,                  // Numero di record per batch
            'max_per_run' => 20          // Numero massimo di record da processare in una singola esecuzione
        ],
        'state_file' => [
            'path' => __DIR__ . '/../data/sync_state.json',  // Percorso al file di stato
            'backup_path' => __DIR__ . '/../data/sync_state.json.bak',  // Percorso al file di backup
        ],
        'time_window' => [
            'enabled' => true,             // Abilita la finestra temporale per il "doppio binario"
            'default_start' => '2023-01-01',  // Data di inizio predefinita
            'default_end' => '2023-12-31',    // Data di fine predefinita
            'update_field' => 'lastupdatedate' // Campo da verificare per gli aggiornamenti
        ],
        'lock' => [
            'file' => __DIR__ . '/../data/sync.lock',  // File di lock per evitare esecuzioni concorrenti
            'timeout' => 3600                         // Tempo massimo di esecuzione in secondi
        ]
    ]    
];