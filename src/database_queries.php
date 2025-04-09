<?php


// Ottiene i dati dei contatti in modo incrementale usando il "doppio binario"
 
function getContactDataIncrementalSync($db, $prefix_table, $prefix_field, $lastId, $offset, $limit, $startDate, $endDate) {
    // Prepara la query per il "doppio binario":
    // 1. Importa nuovi record con ID > lastId, per il tracciamento
    // 2. indipendentemente dal loro ID, importa record modificati tra startDate e endDate, per la gestione di lastupdatedate
    $sql = "SELECT u.*, c.* 
            FROM {$prefix_table}users u
            JOIN {$prefix_table}comprofiler c ON u.id = c.user_id
            WHERE (u.id > :lastId) 
               OR (c.lastupdatedate BETWEEN :startDate AND :endDate 
                  AND c.lastupdatedate != '0000-00-00 00:00:00')
            ORDER BY u.id ASC
            LIMIT :offset, :limit";
    
    // Debug
    // var_dump($sql, $lastId, $offset, $limit, $startDate, $endDate); die;
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':lastId', $lastId, PDO::PARAM_INT);
    $stmt->bindValue(':startDate', $startDate, PDO::PARAM_STR);
    $stmt->bindValue(':endDate', $endDate, PDO::PARAM_STR);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT); //limit corrisponde a $batch_size
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}