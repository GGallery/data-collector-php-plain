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
        'name' => '',
        'prefix_token' => '', 
    ],
    'encryption' => [
        'key' => '', // Chiave di crittografia
        'iv' => '', // IV di crittografia
    ],
    'api' => [
        'contacts_url' => 'http://localhost:8000/api/contacts',
        'contacts_extra_url' => 'http://localhost:8000/api/contacts_extra', 
    ],
];