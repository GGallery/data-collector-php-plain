<?php

require_once __DIR__ . '/ApiClient.php';

/**
 * Classe per la gestione e l'invio dei log di errore al server
 */
class ErrorLogger
{
    private $errors = [];
    private $systemLogUrl;
    private $token;
    private $platformName;
    

    public function __construct($systemLogUrl, $token, $platformName)
    {
        $this->systemLogUrl = $systemLogUrl;
        $this->token = $token;
        $this->platformName = $platformName;
    }
    
    // Registra un errore e lo invia al server
    public function log($file, $function, $message, $email, $errorType = 'client')
    {
        // Aggiunge l'errore all'array
        $this->errors[] = [
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
            'message' => json_encode(['errors' => $this->errors], JSON_PRETTY_PRINT),
            'email' => $email,
            'platform_name' => $this->platformName
        ];
        
        // Invia i dati al server
        try {
            $apiClient = new ApiClient($this->systemLogUrl, $this->token);
            $response = $apiClient->sendData($logData);
            
            // Log locale per debug
            echo "Errore registrato: " . $message . " per " . $email . " in " . $function . PHP_EOL;
            
            return true;
        } catch (Exception $e) {
            // In caso di errore durante l'invio del log, registra localmente
            echo "Errore durante l'invio del log: " . $e->getMessage() . PHP_EOL;
            return false;
        }
    }
    
    // Ottieni tutti gli errori registrati
    public function getErrors()
    {
        return $this->errors;
    }
    
    // Pulisce l'array degli errori
    public function clearErrors()
    {
        $this->errors = [];
    }
}