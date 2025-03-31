<?php

class SyncStateManager {
    private $stateFilePath;
    private $platformPrefix;
    private $backupPath;
    
    public function __construct($stateFilePath, $platformPrefix) {
        $this->stateFilePath = $stateFilePath;
        $this->platformPrefix = $platformPrefix;
        $this->backupPath = $stateFilePath . '.bak';
    }
    
    /**
     * Carica lo stato di sincronizzazione attuale
     */
    public function loadState() {
        // Stato predefinito
        $defaultState = [
            'platform_prefix' => $this->platformPrefix,
            'last_id_processed' => 0,
            'last_sync_date' => null,
            'last_update_date' => null,
            'offset' => 0,
            'processed_records' => 0,
            'success_count' => 0,
            'error_count' => 0
        ];
        
        // Se il file non esiste, restituisci lo stato predefinito
        if (!file_exists($this->stateFilePath)) {
            return $defaultState;
        }
        
        // Leggi il contenuto del file
        $jsonContent = file_get_contents($this->stateFilePath);
        if (empty($jsonContent)) {
            return $defaultState;
        }
        
        // Decodifica il JSON
        $state = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Log dell'errore
            error_log("Errore nella decodifica dello stato JSON: " . json_last_error_msg());
            return $defaultState;
        }
        
        // Debug
        // var_dump($state); die;
        
        // Garantisce che tutti i campi necessari siano presenti
        return array_merge($defaultState, $state);
    }
    
    /**
     * Salva lo stato di sincronizzazione
     */
    public function saveState($state) {
        // Assicurati che lo stato contenga il platformPrefix
        $state['platform_prefix'] = $this->platformPrefix;
        
        // Crea una copia di backup se il file esiste
        if (file_exists($this->stateFilePath)) {
            copy($this->stateFilePath, $this->backupPath);
        }
        
        // Codifica lo stato in JSON
        $jsonContent = json_encode($state, JSON_PRETTY_PRINT);
        
        // Scrivi su un file temporaneo
        $tempFile = $this->stateFilePath . '.tmp';
        file_put_contents($tempFile, $jsonContent);
        
        // Esegui una scrittura atomica rinominando il file
        if (!rename($tempFile, $this->stateFilePath)) {
            // Fallback: Se la rinomina fallisce, prova con la copia e l'eliminazione
            copy($tempFile, $this->stateFilePath);
            unlink($tempFile);
        }
        
        // Debug
        // var_dump("Stato salvato", $state); die;
        
        return true;
    }
}