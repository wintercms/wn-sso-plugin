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
        'invalid_state' => "État erronée: la requête ne provient pas du fournisseur de connexion unique (SSO)",
        'inactive_provider' => "Le fournisseur de connextion unique :provider n'est pas activé",
        'misconfigured_provider' => "Le fournisseur de connextion unique :provider n'est pas configuré correctement.",
        'user_not_found' => "L'usager :user: n'existe pas.",
    ],
    'models' => [
        'general' => [
            'id' => 'ID',
            'created_at' => 'Création',
            'updated_at' => 'Mise à jour',
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
        'provider' => [
            'label' => 'Fournisseur SSO',
            'label_plural' => 'Fournisseurs SSO',
        ],
    ],
    'provider_btn' => [
        'label' => 'Connexion avec :provider',
        'alt_text' => ':provider logo',
    ],
];
