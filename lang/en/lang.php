<?php

return [
    'plugin' => [
        'name' => 'SSO',
        'description' => 'Adds support for OAuth-based Single Sign On (SSO) to the Winter CMS backend module through the use of Laravel Socialiate.',
    ],
    'permissions' => [
        'view_logs' => 'View logs',
    ],
    'messages' => [
        'already_logged_in' => 'You are already logged in. Please log out first.',
        'connection_not_allowed' => ":email: connection not allowed for :provider SSO Provider.",
        'invalid_ssoid' => ':email: Id mismatch for :provider SSO Provider.',
        'invalid_state' => 'Invalid state: request is not from :provider SSO Provider.',
        'inactive_provider' => 'The :provider: SSO Provider is not enabled.',
        'misconfigured_provider' => 'The :provider: SSO Provider is not properly configured.',
        'register_aborted' => 'New user registration has been aborted by an event handler.',
        'signin_aborted' => 'Sign-in has been aborted by an event handler for the :provider SSO Provider.',
        'user_not_found' => ':user: user not found.',
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
