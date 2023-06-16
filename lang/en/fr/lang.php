<?php

return [
    'plugin' => [
        'name' => 'SSO',
        'description' => 'Ajout de la prise en charge de l\'authentification unique (SSO) basée sur OAuth au module backend du CMS Winter grâce à l\'utilisation de Laravel Socialiate..',
    ],
    'permissions' => [
        'view_logs' => 'Voir les logs',
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
    'providers' => [
        'bitbucket' => 'Bitbucket',
        'facebook' => 'Facebook',
        'github' => 'Github',
        'gitlab' => 'GitLab',
        'google' => 'Google',
        'linkedin' => 'LinkedIn',
        'twitter' => 'Twitter',
    ],
    'provider_btn' => [
        'label' => 'Connexion avec :provider',
        'alt_text' => ':provider logo',
    ],
];
