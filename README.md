# Winter.SSO - Single Sign-On Plugin

[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/wintercms/wn-sso-plugin/blob/main/LICENSE)

Adds OAuth-based Single Sign-On (SSO) authentication to the Winter CMS backend using [Laravel Socialite](https://github.com/laravel/socialite). Allow your backend users to authenticate using their existing accounts from providers like Google, GitHub, Microsoft 365, and more.

## Features

- **8 Built-in Providers**: GitHub, Google, Facebook, GitLab, Bitbucket, LinkedIn, Twitter (OAuth 1.0 & 2.0)
- **Extensible**: Easy integration with 100+ community [Socialite Providers](https://socialiteproviders.com/)
- **Provider Plugins**: Install additional providers as separate plugins (e.g., Winter.SSOProviderMicrosoft)
- **Event System**: Comprehensive hooks for customizing authentication flow
- **Security**: SSO ID verification, email normalization, IP logging
- **User Registration**: Optionally create new users via SSO
- **Native Auth Control**: Disable username/password login, enforce SSO-only
- **Audit Logging**: Track all SSO authentication attempts
- **Flexible Configuration**: Environment-based or file-based setup

## Installation

Install via [Composer](http://getcomposer.org/):

```bash
composer require winter/wn-sso-plugin
php artisan migrate
```

If using a [public folder](https://wintercms.com/docs/develop/docs/setup/configuration#using-a-public-folder), republish assets:

```bash
php artisan winter:mirror
```

## Quick Start

Let's set up GitHub authentication as an example:

### 1. Create OAuth App on GitHub

1. Go to [GitHub Developer Settings](https://github.com/settings/developers)
2. Click "New OAuth App"
3. Fill in the details:
   - **Application name**: Your Site Name
   - **Homepage URL**: `https://example.com`
   - **Authorization callback URL**: `https://example.com/backend/winter/sso/handle/callback/github`
4. Save and copy your **Client ID** and **Client Secret**

### 2. Configure Environment Variables

Add to your `.env` file:

```env
GITHUB_CLIENT_ID=your_client_id_here
GITHUB_CLIENT_SECRET=your_client_secret_here
```

### 3. Enable the Provider

Edit `config/winter/sso/config.php` (create if it doesn't exist):

```php
<?php
return [
    'enabled_providers' => [
        'github',
    ],
];
```

### 4. Test

Visit `/backend/auth/signin` - you'll see a "Sign in with GitHub" button!

## Configuration Reference

Configuration file: `config/winter/sso/config.php`

### Core Settings

```php
<?php
return [
    /**
     * List of enabled providers
     * Only providers in this array will appear on the login page
     */
    'enabled_providers' => [
        'google',
        'github',
        // Add more providers here
    ],

    /**
     * Disable native username/password authentication
     * Forces users to authenticate via SSO only
     * If only one provider is enabled, redirects directly to it
     */
    'prevent_native_auth' => false,

    /**
     * Allow new user registration via SSO
     * If false, only existing users can login via SSO
     */
    'allow_registration' => false,

    /**
     * Require explicit permission for SSO connections
     * Users must explicitly allow each provider (requires backend UI - TODO)
     */
    'require_explicit_permission' => false,

    /**
     * Default role for SSO-registered users
     * Role code to assign to new users (TODO: not yet implemented)
     */
    'default_role' => null,

    /**
     * Provider-specific configuration
     * Credentials are typically loaded from environment variables
     */
    'providers' => [
        'github' => [
            'client_id' => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
            'guzzle' => [], // HTTP client options
        ],
        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'guzzle' => [],
        ],
        // Add more providers...
    ],
];
```

### Redirect URL Format

All providers use this URL pattern:
```
https://example.com/backend/winter/sso/handle/callback/{provider}
```

Examples:
- GitHub: `/backend/winter/sso/handle/callback/github`
- Google: `/backend/winter/sso/handle/callback/google`
- Microsoft: `/backend/winter/sso/handle/callback/microsoft`

## Built-in Providers

These providers are supported out of the box by Laravel Socialite:

| Provider | Config Key | Setup Guide |
|----------|-----------|-------------|
| Bitbucket | `bitbucket` | [Bitbucket OAuth](https://support.atlassian.com/bitbucket-cloud/docs/use-oauth-on-bitbucket-cloud/) |
| Facebook | `facebook` | [Facebook Login](https://developers.facebook.com/docs/facebook-login) |
| GitHub | `github` | [GitHub OAuth Apps](https://github.com/settings/developers) |
| GitLab | `gitlab` | [GitLab OAuth](https://docs.gitlab.com/ee/integration/oauth_provider.html) |
| Google | `google` | [Setup Guide](docs/providers/google.md) |
| LinkedIn (OpenID) | `linkedin-openid` | [LinkedIn OAuth](https://learn.microsoft.com/en-us/linkedin/shared/authentication/authentication) |
| Twitter (OAuth 1.0) | `twitter` | [Twitter OAuth](https://developer.twitter.com/en/docs/authentication/oauth-1-0a) |
| Twitter (OAuth 2.0) | `twitter-oauth-2` | [Twitter OAuth 2.0](https://developer.twitter.com/en/docs/authentication/oauth-2-0) |

## Adding Additional Providers

### Option 1: Provider Plugins (Recommended)

Provider plugins package everything needed for a specific provider:

```bash
# Example: Install Microsoft 365 provider
composer require winter/wn-ssoprovidermicrosoft-plugin
```

Provider plugins automatically:
- Install the Socialite provider package
- Register with Laravel Socialite
- Add configuration options
- Include provider logos and assets

See [Creating Provider Plugins](docs/creating-provider-plugins.md) for building your own.

### Option 2: Direct Socialite Providers

Use any provider from [SocialiteProviders.com](https://socialiteproviders.com/):

1. **Install the provider package**:
   ```bash
   composer require socialiteproviders/microsoft
   ```

2. **Register the provider** in your plugin's `boot()` method:
   ```php
   Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
       $event->extendSocialite('microsoft', \SocialiteProviders\Microsoft\Provider::class);
   });
   ```

3. **Add configuration**:
   ```php
   // config/winter/sso/config.php
   'enabled_providers' => ['microsoft'],
   'providers' => [
       'microsoft' => [
           'client_id' => env('MICROSOFT_CLIENT_ID'),
           'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
           'tenant' => env('MICROSOFT_TENANT', 'common'),
       ],
   ],
   ```

4. **Add provider logo** (optional):
   Place an SVG at `/plugins/winter/sso/assets/images/providers/microsoft.svg`

## Events System

The plugin fires events at every stage of the authentication flow, allowing you to customize behavior:

### Available Events

Each event is provider-specific. Replace `{provider}` with your provider name (e.g., `google`, `github`).

#### 1. `winter.sso.{provider}.authenticating`

Fires before OAuth authentication begins. Return `false` to abort.

```php
Event::listen('winter.sso.google.authenticating', function () {
    // Check if authentication should proceed
    if (!some_condition()) {
        return false; // Aborts authentication
    }
});
```

#### 2. `winter.sso.{provider}.authenticated`

Fires after successful OAuth, before user lookup.

```php
Event::listen('winter.sso.google.authenticated', function ($ssoUser) {
    // $ssoUser is Laravel\Socialite\AbstractUser
    Log::info('User authenticated via Google', [
        'email' => $ssoUser->getEmail(),
        'id' => $ssoUser->getId(),
    ]);
});
```

#### 3. `winter.sso.{provider}.beforeRegister`

Fires before creating a new user account. Throw exception to prevent registration.

```php
Event::listen('winter.sso.google.beforeRegister', function ($ssoUser) {
    // Only allow company email addresses
    if (!str_ends_with($ssoUser->getEmail(), '@mycompany.com')) {
        throw new AuthenticationException('Only company emails allowed');
    }
});
```

#### 4. `winter.sso.{provider}.registered`

Fires after new user is created. Populate additional fields here.

```php
Event::listen('winter.sso.google.registered', function ($user, $ssoUser) {
    // $user is Backend\Models\User
    // $ssoUser is Laravel\Socialite\AbstractUser
    $user->fill([
        'first_name' => $ssoUser->user['given_name'] ?? null,
        'last_name' => $ssoUser->user['family_name'] ?? null,
    ]);
    $user->save();
});
```

#### 5. `winter.sso.{provider}.beforeLogin`

Fires before session is created.

```php
Event::listen('winter.sso.google.beforeLogin', function ($user, $ssoUser) {
    // Perform checks before allowing login
    if ($user->is_suspended) {
        throw new AuthenticationException('Account suspended');
    }
});
```

#### 6. `winter.sso.{provider}.afterLogin`

Fires after successful login and session creation.

```php
Event::listen('winter.sso.google.afterLogin', function ($user, $ssoUser) {
    // Track login
    Log::info('User logged in via Google', ['user_id' => $user->id]);

    // Update last login timestamp
    $user->last_sso_login = now();
    $user->save();
});
```

### Accessing SSO Data

User SSO data is stored in the user's metadata:

```php
// Get SSO data for a specific provider
$googleId = $user->getSsoValue('google', 'id');
$token = $user->getSsoValue('google', 'token');

// Set SSO data
$user->setSsoValues('google', [
    'id' => '12345',
    'token' => 'abc123',
    'custom_field' => 'value',
]);
```

Metadata structure: `Backend\Models\User::metadata['winter.sso'][$provider][$key]`

## Security Features

### SSO ID Verification

Once a user connects via a provider, their SSO ID is stored. On subsequent logins, the ID must match. This prevents account takeover if someone else registers the same email with a different provider.

```php
// First login: ID stored
$user->setSsoValues('google', ['id' => '123456']);

// Later login: ID must match
if ($ssoUser->getId() !== $user->getSsoValue('google', 'id')) {
    throw new InvalidSsoIdException();
}
```

### Email Normalization

Emails are normalized to prevent duplicate accounts:

- **Gmail**: Dots are removed from usernames (`john.doe@gmail.com` → `johndoe@gmail.com`)
- **All domains**: Lowercased

### IP Logging

All authentication attempts are logged with:
- Provider used
- Action taken
- User ID
- SSO provider ID
- Email provided
- IP address
- Metadata (remember me, etc.)

View logs: **Settings → Logs → SSO Logs**

### Session Security

The plugin automatically adjusts `session.same_site` from `strict` to `lax` when secure sessions are enabled, ensuring OAuth callbacks work correctly.

## Troubleshooting

### "The provider X is not enabled"

**Cause**: Provider not in `enabled_providers` array.

**Solution**: Add provider to config:
```php
'enabled_providers' => ['github', 'google'],
```

### "Invalid state"

**Cause**: Session lost between redirect and callback, or CSRF protection too strict.

**Solutions**:
- Ensure sessions are working correctly
- Check `session.same_site` setting (plugin auto-adjusts to `lax`)
- Clear browser cookies and try again

### "Email not found"

**Cause**: User doesn't exist and `allow_registration` is `false`.

**Solution**: Either:
- Create the user account manually in the backend
- Enable registration: `'allow_registration' => true`

### "Invalid SSO ID"

**Cause**: User previously connected with a different account from the same provider.

**Solution**: This is a security feature. The user must use the original account, or an admin must clear the SSO data:
```php
$user->setSsoValues('provider', ['id' => null]);
```

### SSO buttons not appearing

**Checklist**:
1. Provider is in `enabled_providers` array
2. `client_id` is set in provider config
3. Environment variables are loaded correctly
4. Assets are published (`php artisan winter:mirror`)

### Provider-specific issues

Check the provider's setup guide in `docs/providers/` for common issues.

## Advanced Topics

### Preventing Native Authentication

Force SSO-only login:

```php
'prevent_native_auth' => true,
```

This will:
- Hide the username/password form
- Disable the login AJAX handler
- Show only SSO buttons

If only one provider is enabled, users are redirected directly to that provider.

### Customizing Button Appearance

Override button configuration per provider:

```php
'providers' => [
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'button' => [
            'label' => 'Custom Button Text',
            'view' => 'your.custom.view', // Custom button template
        ],
    ],
],
```

### Provider Scopes

Request additional OAuth scopes:

```php
'providers' => [
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'scopes' => ['user:email', 'read:user'],
    ],
],
```

### HTTP Client Options (Guzzle)

Configure HTTP client for providers behind proxies or with special requirements:

```php
'providers' => [
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'guzzle' => [
            'timeout' => 10,
            'proxy' => 'http://proxy.example.com:8080',
        ],
    ],
],
```

## Further Documentation

- [Architecture Overview](docs/architecture.md) - How the plugin works internally
- [Creating Provider Plugins](docs/creating-provider-plugins.md) - Build custom provider plugins
- [Google Setup Guide](docs/providers/google.md) - Detailed Google OAuth setup

## Contributing

Contributions are welcome! Please submit pull requests to the [Winter CMS repository](https://github.com/wintercms/winter).

## License

This plugin is licensed under the [MIT License](LICENSE).
