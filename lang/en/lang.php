<?php

return [
    'plugin' => [
        'name' => 'SSO',
        'description' => 'Adds support for OAuth-based Single Sign On (SSO) to the Winter CMS backend module through the use of Laravel Socialiate.',
    ],
    'permissions' => [
        'view_logs' => 'View logs',
    ],
    'errors' => [
        'already_logged_in' => 'You are already logged in. Please log out first.',
        'authentication_aborted' => 'Authentication has been aborted by an event handler for the :provider SSO Provider.',
        'email_not_found' => 'The provided email :email does not exist.',
        'invalid_ssoid' => ':email: ID mismatch for :provider SSO Provider.',
        'invalid_state' => 'Invalid state: request is not from :provider SSO Provider.',
        'missing_client_id' => 'The : SSO provider does not have a client_id configured.',
        'provider_blocked' => "The :provider SSO provider is not enabled for :email.",
        'provider_disabled' => "The :provider SSO provider is not enabled.",
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
    'providers' => [
        'bitbucket' => 'Bitbucket',
        'facebook' => 'Facebook',
        'github' => 'Github',
        'gitlab' => 'GitLab',
        'google' => 'Google',
        'linkedin-openid' => 'LinkedIn (OpenID)',
        'twitter' => 'Twitter',
    ],
    'provider_btn' => [
        'label' => 'Sign in with :provider',
        'alt_text' => ':provider logo',
    ],
];
