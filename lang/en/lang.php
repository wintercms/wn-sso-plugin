<?php

return [
    'plugin' => [
        'name' => 'SSO',
        'description' => 'Adds support for OAuth-based Single Sign On (SSO) to the Winter CMS backend module through the use of Laravel Socialiate.',
    ],
    'permissions' => [
        'view_logs' => 'View logs',
    ],
    'models' => [
        'general' => [
            'id' => 'ID',
            'created_at' => 'Created At',
        ],
        'log' => [
            'label' => 'Log',
            'label_plural' => 'SSO Logs',
            'menu_description' => 'View logged Single Sign On interactions.',
            'provider' => 'Provider',
            'action' => 'Action',
            'user' => 'User',
            'ip' => 'IP Address',
            'provided_id' => 'Provided ID',
            'provided_email' => 'Provided Email',
        ],
    ],
];
