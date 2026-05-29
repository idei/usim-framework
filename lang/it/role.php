<?php

return [
    'root' => [
        'name' => 'Root',
        'description' => 'Utente con accesso totale e incondizionato a tutte le funzionalità.',
    ],
    'admin' => [
        'name' => 'Administrator',
        'description' => 'Gestisce il sistema e gli utenti.',
    ],
    'registered' => [
        'name' => 'Registered',
        'description' => 'Utente registrato con permessi limitati.',
    ],
    'approved' => [
        'name' => 'Approved',
        'description' => 'Utente approvato con accesso a funzionalità aggiuntive.',
    ],
    'translator' => [
        'name' => 'Translator',
        'description' => 'Utente responsabile della gestione delle traduzioni.',
    ],
    'developer' => [
        'name' => 'Developer',
        'description' => 'Utente con accesso a strumenti di sviluppo e debug.',
    ],
];
