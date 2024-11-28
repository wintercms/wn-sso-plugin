<?php

return [
    'plugin' => [
        'name' => 'SSO',
        'description' => 'Ajout de la prise en charge de l\'authentification unique (SSO) basée sur OAuth au module backend du CMS Winter grâce à l\'utilisation de Laravel Socialiate..',
    ],
    'permissions' => [
        'view_logs' => 'Voir les logs',
    ],
    'messages' => [
        'already_logged_in' => "Vous êtes déjà connecté. Veuillez d'abord vous déconnecter.",
        'authentication_aborted' => "La connexion au fournisseur SSO :provider a été avortée par un gestionnaire d'événement.",
        'email_not_found' => "L'usager :email: n'existe pas.",
        'invalid_ssoid' => ":email: disparité de l'identifiant pour le fournisseur SSO :provider.",
        'invalid_state' => "État erronée: la requête ne provient pas du fournisseur SSO :provider",
        'provider_blocked' => ":email: la connexion n'est pas permise pour le fournisseur SSO :provider.",
        'provider_disabled' => "Le fournisseur SSO :provider n'est pas activé",
    ],
    'models' => [
        'general' => [
            'id' => 'ID',
            'created_at' => 'Créer le',
        ],
        'log' => [
            'label' => 'Log',
            'label_plural' => 'SSO Logs',
            'menu_description' => 'Voir les interactions SSO enregistrées.',
            'provider' => 'Fournisseur',
            'action' => 'Action',
            'user' => 'Utilisateur',
            'ip' => 'Adresse IP',
            'provided_id' => 'Identifiant fourni',
            'provided_email' => 'Email fourni',
        ],
    ],
    'provider_btn' => [
        'label' => 'Connexion avec :provider',
        'alt_text' => ':provider logo',
    ],
];
