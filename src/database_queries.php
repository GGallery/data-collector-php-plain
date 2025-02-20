<?php

function getContactData($db_source, $email, $prefix_table, $prefix_field) {
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
        WHERE {$prefix_table}users.email = :email
    ";
    $stmt = $db_source->prepare($query); //PDOStatement
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// io devo partire dalla tabella ms_users utilizzando l'email per ottenere l'user_id, e poi fare la join con la tabella ms_comprofiler per ottenere gli altri dati