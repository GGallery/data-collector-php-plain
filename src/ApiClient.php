<?php

class ApiClient
{
    private $api_url;
    private $token;
    
    public function __construct($api_url, $token)
    {
        $this->api_url = $api_url;
        $this->token = $token;
    }
    
    // Invia i dati all'API
    public function sendData($data)
    {
        $ch = curl_init($this->api_url); // Inizializza cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token, // Aggiunge il token all'header
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // Imposta i dati da inviare

        $response = curl_exec($ch); // Esegue la richiesta
        curl_close($ch); // Chiude la sessione cURL

        return $response; // Ritorna la risposta dell'API
    }
}