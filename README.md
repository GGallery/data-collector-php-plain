# Struttura ad albero dei file principali

```
config/
└ config.php

scripts/
└ cron_script.php

src/
└ ApiClient.php
└ database_queries.php
└ EncryptionHelper.php
└ TokenGenerator.php
```


# Componenti

## Commands

`TokenGenerator`
>generateToken()
- Genera un token combinando prefisso e timestamp
- Cripta il token usando `EncryptionHelper`
- Ritorna il token criptato per l'autenticazione API


`ApiClient`
>sendData()

- Inizializza una connessione cURL
- Aggiunge il token Bearer nell'header
- Invia i dati in formato JSON
- Gestisce la risposta dell'API


`database_queries`

- Recupera i dati dell'utente da `ms_users`
- Esegue join con `ms_comprofiler`
- Filtra per email
- Ritorna i dati dell'utente e i dettagli aggiuntivi


`config`

- Database sorgente
- Database destinazione
- Prefissi delle tabelle e dei campi
- Chiavi di crittografia
- URL degli endpoint API


`cron_script`

- Carica le configurazioni dal file `config`
- Chiama i metodi degli altri file
- Stabilisce connessioni ai database sorgente e destinazione
- Genera il token di autenticazione
- Invia i dati a due endpoint API