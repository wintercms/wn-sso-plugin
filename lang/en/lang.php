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
        'invalid_state' => 'Invalid state: request is not from the SSO Provider.',
        'inactive_provider' => 'This Single Sign On Provider is not enabled.',
        'misconfigured_provider' => 'This Single Sign On Provider is not properly configured.',
        'user_not_found' => ':user: user not found.',
    ],
    'models' => [
        'general' => [
            'id' => 'ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
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
        'provider' => [
            'label' => 'SSO Provider',
            'label_plural' => 'SSO Providers',
            'logo' => 'Logo',
            'client_id' => 'Client ID',
            'client_secret' => 'Client Secret',
            'empty_option' => '-- Select Provider --',
            'is_enabled' => 'Is Enabled',
            'menu_description' => 'Configure Single Sign On Providers',
            'name' => 'Name',
            'scopes' => 'Extra Scopes',
            'slug' => 'Slug',
        ],
    ],
    'providers' => [
        'bitbucket' => 'Bitbucket',
        'facebook' => 'Facebook',
        'github' => 'Github',
        'gitlab' => 'GitLab',
        'google' => 'Google',
        'linkedin' => 'LinkedIn',
        'linkedin-openid' => 'LinkedIn (OpenID)',
        'twitter' => 'Twitter',
    ],
    'provider_btn' => [
        'label' => 'Sign in with :provider',
        'alt_text' => ':provider logo',
    ],
];
