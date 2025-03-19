<?php

function getContactData($db_source, $prefix_table, $prefix_field, $offset, $limit, $startDate = null, $endDate = null) {
    $query = "
        SELECT 
            {$prefix_table}users.email,
            {$prefix_table}comprofiler.{$prefix_field}cognome, 
            {$prefix_table}comprofiler.{$prefix_field}codicefiscale, 
            {$prefix_table}comprofiler.{$prefix_field}datadinascita, 
            {$prefix_table}comprofiler.{$prefix_field}luogodinascita,
            {$prefix_table}comprofiler.{$prefix_field}provinciadinascita,
            {$prefix_table}comprofiler.{$prefix_field}indirizzodiresidenza,
            {$prefix_table}comprofiler.{$prefix_field}provdiresidenza,
            {$prefix_table}comprofiler.{$prefix_field}cap,
            {$prefix_table}comprofiler.{$prefix_field}telefono,
            {$prefix_table}comprofiler.{$prefix_field}nome,
            {$prefix_table}comprofiler.{$prefix_field}citta,
            {$prefix_table}comprofiler.{$prefix_field}professionedisciplina,
            {$prefix_table}comprofiler.{$prefix_field}ordine,
            {$prefix_table}comprofiler.{$prefix_field}numeroiscrizione,
            {$prefix_table}comprofiler.{$prefix_field}reclutamento,
            {$prefix_table}comprofiler.{$prefix_field}codicereclutamento,
            {$prefix_table}comprofiler.{$prefix_field}professione,
            {$prefix_table}comprofiler.{$prefix_field}profiloprofessionale,
            {$prefix_table}comprofiler.{$prefix_field}settore,
            {$prefix_table}comprofiler.{$prefix_field}societa
        FROM {$prefix_table}users
        INNER JOIN {$prefix_table}comprofiler 
        ON {$prefix_table}comprofiler.user_id = {$prefix_table}users.id
        WHERE 1=1
    ";
    
    if ($startDate && $endDate) {
        $query .= " AND ({$prefix_table}comprofiler.lastupdatedate BETWEEN :startDate AND :endDate)";
    }

    $query .= " ORDER BY {$prefix_table}comprofiler.lastupdatedate DESC
                LIMIT :limit OFFSET :offset";

    $stmt = $db_source->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    if ($startDate && $endDate) {
        $stmt->bindParam(':startDate', $startDate);
        $stmt->bindParam(':endDate', $endDate);
    }

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Ottiene i dati dei contatti in modo incrementale usando il "doppio binario"
 
function getContactDataIncrementalSync($db, $prefix_table, $prefix_field, $lastId, $offset, $limit, $startDate, $endDate) {
    // Prepara la query per il "doppio binario":
    // 1. Nuovi record con ID > lastId
    // 2. Record aggiornati con data_update tra startDate e endDate
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
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}