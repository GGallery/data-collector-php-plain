<?php

// Ottiene i dati dei contatti in modo incrementale usando il "doppio binario"
function getContactDataIncrementalSync($db, $prefix_table, $prefix_field, $lastId, $offset, $limit, $startDate, $endDate) {
    // Array che conterrà tutti i risultati
    $allResults = [];
    $totalRecords = 0;
    
    // Calcola quanti record recuperare da ciascuna query
    $halfLimit = ceil($limit / 2);
    
    // ---- QUERY 1: RECUPERA I NUOVI RECORD (ID > lastId) ----
    $sqlNew = "SELECT u.*, c.* 
               FROM {$prefix_table}users u
               JOIN {$prefix_table}comprofiler c ON u.id = c.user_id
               WHERE u.id > :lastId
               ORDER BY u.id ASC
               LIMIT :limit";
    
    $stmtNew = $db->prepare($sqlNew);
    $stmtNew->bindValue(':lastId', $lastId, PDO::PARAM_INT);
    $stmtNew->bindValue(':limit', $halfLimit, PDO::PARAM_INT);
    $stmtNew->execute();
    
    $newRecords = $stmtNew->fetchAll(PDO::FETCH_ASSOC);
    
    // Aggiungi il flag 'record_type' = 'new' ai nuovi record
    foreach ($newRecords as &$record) {
        $record['record_type'] = 'new';
        $allResults[] = $record;
        $totalRecords++;
    }
    
    // Calcola il limite rimanente per i record aggiornati
    $remainingLimit = $limit - count($newRecords);
    
    if ($remainingLimit > 0) {
        // ---- QUERY 2: RECUPERA I RECORD AGGIORNATI (lastupdatedate tra startDate e endDate) ----
        // Escludi i record che hai già ottenuto nella prima query
        $sqlUpdated = "SELECT u.*, c.* 
                      FROM {$prefix_table}users u
                      JOIN {$prefix_table}comprofiler c ON u.id = c.user_id
                      WHERE u.id <= :lastId
                      AND c.lastupdatedate BETWEEN :startDate AND :endDate 
                      AND c.lastupdatedate != '0000-00-00 00:00:00'
                      ORDER BY c.lastupdatedate DESC, u.id ASC
                      LIMIT :offset, :limit";
        
        $stmtUpdated = $db->prepare($sqlUpdated);
        $stmtUpdated->bindValue(':lastId', $lastId, PDO::PARAM_INT);
        $stmtUpdated->bindValue(':startDate', $startDate, PDO::PARAM_STR);
        $stmtUpdated->bindValue(':endDate', $endDate, PDO::PARAM_STR);
        $stmtUpdated->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmtUpdated->bindValue(':limit', (int)$remainingLimit, PDO::PARAM_INT);
        $stmtUpdated->execute();
        
        $updatedRecords = $stmtUpdated->fetchAll(PDO::FETCH_ASSOC);
        
        // Aggiungi il flag 'record_type' = 'updated' ai record aggiornati
        foreach ($updatedRecords as &$record) {
            $record['record_type'] = 'updated';
            $allResults[] = $record;
            $totalRecords++;
        }
    }
    
    // Debug
    // echo "Totale: " . count($allResults) . " (Nuovi: " . count($newRecords) . ", Aggiornati: " . (isset($updatedRecords) ? count($updatedRecords) : 0) . ")\n";
    
    return $allResults;
}