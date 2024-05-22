<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prevent Native Authentication
    |--------------------------------------------------------------------------
    |
    | If true, will prevent users from logging in with their username / email
    | and password directly; forcing them to use a configured SSO provider.
    | When only one provider is enabled, the login page will be redirected
    | to the provider's login page.
    |
    | @TODO: Implement this
    |
    */

    'prevent_native_auth' => env('SSO_PREVENT_NATIVE_AUTH', false),

    'require_explicit_permission' => env('SSO_REQUIRE_EXPLICIT_PERMISSION', false),

    /*
    |--------------------------------------------------------------------------
    | Enabled Providers
    |--------------------------------------------------------------------------
    |
    | List of Socialite providers that are currently enabled.
    |
    */

    'enabled_providers' => [
        // 'bitbucket',
        // 'facebook',
        // 'github',
        // 'gitlab',
        // 'google',
        // 'linkedin-openid',
        // 'twitter',
        // 'twitter-oauth-2',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allow Registration
    |--------------------------------------------------------------------------
    |
    | If true, will allow users to register for an account using a configured
    | SSO provider. If false, will only allow existing users to login.
    |
    | @TODO: Implement this
    |
    */

    'allow_registration' => env('SSO_ALLOW_REGISTRATION', false),

    /*
    |--------------------------------------------------------------------------
    | Default Role for New Users
    |--------------------------------------------------------------------------
    |
    | The default role code to assign to new users that register using SSO. If
    | null, no role will be assigned. The value must be a valid role code.
    | The winter.sso.user.beforeRegister event is also available.
    |
    | Example: 'default_role' => \Backend\Models\UserRole::CODE_PUBLISHER,
    |
    | @TODO: Implement this
    |
    */

    'default_role' => null,

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This config item is for storing the credentials for third party services
    | that will be integrated with, such as GitHub, Facebook, Twitter, etc.
    | These values will be mirrored to the services.* config namespace.
    |
    */

    'providers' => [

        'bitbucket' => [
            'client_id' => env('BITBUCKET_CLIENT_ID'),
            'client_secret' => env('BITBUCKET_CLIENT_SECRET'),
            'guzzle' => [],
        ],

        'facebook' => [
            'client_id' => env('FACEBOOK_CLIENT_ID'),
            'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
            'guzzle' => [],
        ],

        'github' => [
            'client_id' => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
            'guzzle' => [],
        ],

        'gitlab' => [
            'client_id' => env('GITLAB_CLIENT_ID'),
            'client_secret' => env('GITLAB_CLIENT_SECRET'),
            'guzzle' => [],
        ],

        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'guzzle' => [],
        ],

        'linkedin-openid' => [
            'client_id' => env('LINKEDIN_OPENID_CLIENT_ID'),
            'client_secret' => env('LINKEDIN_OPENID_CLIENT_SECRET'),
            'guzzle' => [],
        ],

        'twitter' => [
            'client_id' => env('TWITTER_CLIENT_ID'),
            'client_secret' => env('TWITTER_CLIENT_SECRET'),
            'guzzle' => [],
        ],

        'twitter-oauth-2' => [
            'client_id' => env('TWITTER_OAUTH_2_CLIENT_ID'),
            'client_secret' => env('TWITTER_OAUTH_2_CLIENT_SECRET'),
            'guzzle' => [],
        ],

    ],
];
