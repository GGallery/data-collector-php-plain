<?php

function getContactData($db, $email) {
    $prefix_table = $db->prefix_table;
    $prefix_field = $db->prefix_field;
    $query = "
        SELECT 
            {$prefix_table}users.email,
            {$prefix_table}comprofiler.{$prefix_field}cognome, 
            {$prefix_table}comprofiler.{$prefix_field}codicefiscale, 
            {$prefix_table}comprofiler.{$prefix_field}datadinascita, 
            {$prefix_table}comprofiler.{$prefix_field}luogodinascita
        FROM {$prefix_table}users
        INNER JOIN {$prefix_table}comprofiler 
        ON {$prefix_table}comprofiler.user_id = {$prefix_table}users.id
        WHERE {$prefix_table}users.email = :email
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// io devo partire dalla tabella ms_users utilizzando l'email per ottenere l'user_id, e poi fare la join con la tabella ms_comprofiler per ottenere gli altri dati