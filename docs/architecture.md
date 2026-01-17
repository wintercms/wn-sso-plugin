# Winter.SSO Architecture

This document describes the internal architecture of the Winter.SSO plugin, including how it integrates with Laravel Socialite, the authentication flow, configuration system, and extension points.

## Overview

Winter.SSO bridges Winter CMS's backend authentication system with Laravel Socialite's OAuth implementation. The plugin:

1. Registers Laravel Socialite as a service provider
2. Maps Winter.SSO configuration to Laravel's `services` config
3. Extends the backend auth controller to add SSO buttons
4. Handles OAuth redirect/callback flow
5. Matches/creates users based on email
6. Fires events for customization at every step

## Integration with Laravel Socialite

### Service Provider Registration

In `Plugin.php::register()`:

```php
protected function registerSocialite(): void
{
    $this->app->register(SocialiteServiceProvider::class);
    $this->app->alias('Socialite', Socialite::class);
}
```

This makes the `Socialite` facade available throughout the application.

### Configuration Mapping

Socialite expects provider configuration in `config/services.php` under each provider's key. Winter.SSO handles this mapping automatically in `Plugin.php::configureProviders()`:

```php
protected function configureProviders(): void
{
    $providers = Config::get('winter.sso::providers', []);
    $enabledProviders = Config::get('winter.sso::enabled_providers', []);

    foreach ($providers as $provider => $config) {
        if (!in_array($provider, $enabledProviders) || empty($config['client_id'])) {
            continue;
        }

        // Add redirect URL automatically
        $config = array_merge([
            'redirect' => Backend::url('winter/sso/handle/callback/' . $provider),
        ], $config);

        // Map to Socialite's expected config location
        Config::set("services.{$provider}", $config);
    }
}
```

**Configuration Flow:**
1. User sets `config/winter/sso/config.php::providers.github`
2. Plugin reads `winter.sso::providers` at boot time
3. Plugin checks if provider is enabled and has `client_id`
4. Plugin merges redirect URL
5. Plugin copies to `services.github`
6. Socialite reads from `services.github` when `Socialite::with('github')` is called

## OAuth Authentication Flow

### User Journey

```
┌──────────────┐
│ User clicks  │
│ SSO button   │
└──────┬───────┘
       │
       v
┌─────────────────────────────────────────────────┐
│ 1. Redirect Phase                               │
│    /backend/winter/sso/handle/redirect/{provider}│
│    - Validates provider is enabled              │
│    - Checks user not already logged in          │
│    - Redirects to provider's OAuth page         │
└────────┬────────────────────────────────────────┘
         │
         v
┌─────────────────────────────────────────────────┐
│ 2. User authenticates with provider             │
│    (Happens on provider's website)              │
└────────┬────────────────────────────────────────┘
         │
         v
┌─────────────────────────────────────────────────┐
│ 3. Callback Phase                               │
│    /backend/winter/sso/handle/callback/{provider}│
│    - Exchanges code for access token            │
│    - Fetches user data from provider            │
│    - Normalizes email                           │
│    - Finds or creates user                      │
│    - Stores SSO metadata                        │
│    - Creates session                            │
│    - Logs attempt                               │
└────────┬────────────────────────────────────────┘
         │
         v
┌──────────────┐
│ User logged  │
│ in to        │
│ backend      │
└──────────────┘
```

### Redirect Phase

**Controller**: `Handle::redirect($provider)`
**Route**: `/backend/winter/sso/handle/redirect/{provider}`
**Location**: `plugins/winter/sso/controllers/Handle.php:272`

Steps:
1. Validate provider is in `enabled_providers` array
2. Check user is not already logged in
3. Validate `client_id` is configured
4. Call `Socialite::with($provider)->scopes(...)->redirect()`
5. Return redirect response to provider's OAuth page

**Code:**
```php
public function redirect(string $provider): RedirectResponse
{
    // Validation
    if (!in_array($provider, $this->enabledProviders)) {
        return $this->redirectToSignInPage('Provider not enabled');
    }

    // Get scopes from config
    $config = Config::get('services.' . $provider, []);

    // Redirect to provider
    return Socialite::with($provider)
        ->scopes($config['scopes'] ?? [])
        ->redirect();
}
```

### Callback Phase

**Controller**: `Handle::callback($provider)`
**Route**: `/backend/winter/sso/handle/callback/{provider}`
**Location**: `plugins/winter/sso/controllers/Handle.php:63`

Steps:

1. **Validate Request**
   - Provider is enabled
   - OAuth code is present

2. **Fire `winter.sso.{provider}.authenticating` Event**
   - Can return `false` to abort

3. **Exchange Code for User Data**
   ```php
   $ssoUser = Socialite::with($provider)->user();
   ```
   - `$ssoUser` is `Laravel\Socialite\AbstractUser`
   - Contains: `id`, `email`, `name`, `avatar`, `token`, etc.

4. **Fire `winter.sso.{provider}.authenticated` Event**
   - Receives `$ssoUser` parameter

5. **Normalize Email**
   - Lowercase entire email
   - Remove dots from Gmail usernames
   - See `Handle::normalizeEmail()` at line 306

6. **Find or Create User**

   **Option A: User Exists**
   ```php
   $user = $this->authManager->findUserByCredentials(['email' => $email]);
   ```

   Then validate:
   - If `require_explicit_permission` is true, check user allowed this provider
   - If SSO ID already stored, verify it matches current ID (security feature)

   **Option B: User Doesn't Exist**
   - If `allow_registration` is false: throw error
   - If `allow_registration` is true:
     - Fire `winter.sso.{provider}.beforeRegister` event
     - Create user with random password
     - Mark as SSO-created
     - Fire `winter.sso.{provider}.registered` event

7. **Store/Update SSO Metadata**
   ```php
   $user->setSsoValues($provider, [
       'id' => $ssoUser->getId(),
       'token' => $ssoUser->token,
   ]);
   ```

8. **Fire `winter.sso.{provider}.beforeLogin` Event**

9. **Create Session**
   ```php
   $this->authManager->login($user, $remember);
   ```

10. **Fire `winter.sso.{provider}.afterLogin` Event**

11. **Log Authentication**
    - Creates `Winter\SSO\Models\Log` record
    - Includes provider, email, IP, user ID, etc.

12. **Redirect to Backend**
    ```php
    return Backend::redirectIntended('backend');
    ```

## User Matching and Creation

### Email as Login Attribute

The plugin forces email-based login on callback routes:

```php
// plugins/winter/sso/Plugin.php:92
if (str_starts_with(Request::url(), Backend::url('winter/sso/handle/callback/'))) {
    User::$loginAttribute = 'email';
}
```

This ensures `findUserByCredentials(['email' => $email])` works correctly.

### User Creation

When `allow_registration` is true and user doesn't exist:

```php
// plugins/winter/sso/controllers/Handle.php:145
$password = Str::random(400); // Unguessable password
$user = $this->authManager->register(
    credentials: [
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $password,
        'login' => $ssoUser->getNickname() ?: $email,
    ],
    autoLogin: true
);

// Mark as SSO-created (TODO: actually enforce this)
$user->setSsoValues($provider, ['allow_password_auth' => false]);
```

The user is created with a random 400-character password, making password authentication essentially impossible.

### SSO ID Security

After first successful authentication, the provider's ID for the user is stored:

```php
$user->setSsoValues('google', ['id' => '1234567890']);
```

On subsequent logins, the ID must match:

```php
// plugins/winter/sso/controllers/Handle.php:111
$ssoId = $user->getSsoValue($provider, 'id');
if (!is_null($ssoId) && $ssoUser->getId() !== $ssoId) {
    throw new InvalidSsoIdException();
}
```

This prevents account takeover if:
- User A registers with `john@example.com` via Google
- User B later tries to authenticate with same email via Google (but different Google account)

## Metadata Storage

SSO data is stored in `Backend\Models\User::metadata` field (JSON column):

### Structure

```json
{
  "winter.sso": {
    "google": {
      "id": "1234567890",
      "token": "ya29.a0AfH6...",
      "allow_password_auth": false,
      "allowConnection": true,
      "custom_field": "custom_value"
    },
    "github": {
      "id": "987654",
      "token": "gho_abc123..."
    }
  }
}
```

### Helper Methods

Added to `Backend\Models\User` via dynamic methods:

```php
// Get value
$googleId = $user->getSsoValue('google', 'id');
$token = $user->getSsoValue('google', 'token', 'default');

// Set values (saves automatically)
$user->setSsoValues('google', [
    'id' => '123',
    'token' => 'abc',
    'custom' => 'value',
]);
```

**Implementation**: `plugins/winter/sso/Plugin.php:97-109`

## Event System

Events are fired at six points during authentication. Each event is provider-specific.

### Event Naming

Pattern: `winter.sso.{provider}.{stage}`

Examples:
- `winter.sso.google.authenticating`
- `winter.sso.github.registered`
- `winter.sso.microsoft.afterLogin`

### Event Sequence

1. **`winter.sso.{provider}.authenticating`**
   - **When**: Before redirecting to Socialite
   - **Parameters**: None
   - **Return**: `false` to abort
   - **Location**: `Handle.php:73`

2. **`winter.sso.{provider}.authenticated`**
   - **When**: After OAuth, before user lookup
   - **Parameters**: `$ssoUser` (AbstractUser)
   - **Return**: N/A (informational)
   - **Location**: `Handle.php:82`

3. **`winter.sso.{provider}.beforeRegister`**
   - **When**: Before creating new user
   - **Parameters**: `$ssoUser` (AbstractUser)
   - **Throw**: Exception to prevent registration
   - **Location**: `Handle.php:143`
   - **Only fires if**: User doesn't exist and `allow_registration` is true

4. **`winter.sso.{provider}.registered`**
   - **When**: After user created, before login
   - **Parameters**: `$user` (User), `$ssoUser` (AbstractUser)
   - **Return**: N/A
   - **Location**: `Handle.php:173`
   - **Use for**: Setting first/last name, avatar, etc.

5. **`winter.sso.{provider}.beforeLogin`**
   - **When**: Before session creation
   - **Parameters**: `$user` (User), `$ssoUser` (AbstractUser)
   - **Throw**: Exception to prevent login
   - **Location**: `Handle.php:229`

6. **`winter.sso.{provider}.afterLogin`**
   - **When**: After session created
   - **Parameters**: `$user` (User), `$ssoUser` (AbstractUser)
   - **Return**: N/A
   - **Location**: `Handle.php:248`
   - **Use for**: Tracking, notifications, etc.

### Event Examples

```php
// Restrict registration to company emails
Event::listen('winter.sso.google.beforeRegister', function ($ssoUser) {
    if (!str_ends_with($ssoUser->getEmail(), '@mycompany.com')) {
        throw new AuthenticationException('Only company emails allowed');
    }
});

// Populate user fields on registration
Event::listen('winter.sso.google.registered', function ($user, $ssoUser) {
    $user->fill([
        'first_name' => $ssoUser->user['given_name'] ?? null,
        'last_name' => $ssoUser->user['family_name'] ?? null,
    ]);
    $user->save();
});

// Log successful logins
Event::listen('winter.sso.google.afterLogin', function ($user, $ssoUser) {
    Log::info('SSO login', ['user' => $user->email, 'provider' => 'google']);
});
```

## Backend Auth Controller Extension

### Adding SSO Buttons

In `Plugin.php::extendAuthController()`, the plugin listens to `backend.auth.extendSigninView`:

```php
Event::listen('backend.auth.extendSigninView', function ($controller) {
    // Add CSS
    $controller->addCss('/plugins/winter/sso/assets/dist/css/sso.css', 'Winter.SSO');

    // Get enabled providers
    $providers = $this->getProviders();
    $enabledProviders = Config::get('winter.sso::enabled_providers', []);

    // Build provider data
    $processedProviders = [];
    foreach ($enabledProviders as $provider) {
        $processedProviders[$provider] = $providers[$provider]['button'];
    }

    // Save signin URL for redirect after login
    Session::put('signin_url', Request::url());

    // Render buttons
    echo View::make("winter.sso::providers", ['providers' => $processedProviders]);
});
```

### Button Configuration

Each provider has button configuration auto-generated in `Plugin.php::getProviders()`:

```php
$config['button'] = array_merge([
    'view'    => "winter.sso::buttons.provider",
    'logoUrl' => Url::asset('/plugins/winter/sso/assets/images/providers/' . $provider . '.svg'),
    'logoAlt' => Lang::get('winter.sso::lang.provider_btn.alt_text', ['provider' => ucfirst($provider)]),
    'url'     => Backend::url('winter/sso/handle/redirect/' . $provider),
    'label'   => Lang::get('winter.sso::lang.provider_btn.label', ['provider' => ucfirst($provider)]),
], $config['button'] ?? []);
```

Providers can override button config in their provider configuration:

```php
'providers' => [
    'github' => [
        'client_id' => '...',
        'client_secret' => '...',
        'button' => [
            'label' => 'Custom Label',
            'view' => 'my.custom.view',
        ],
    ],
],
```

### Preventing Native Authentication

When `prevent_native_auth` is true:

```php
\Backend\Controllers\Auth::extend(function ($controller) {
    // Visual: Hide login form
    $controller->bindEvent('page.beforeDisplay', function () use ($controller) {
        $controller->addViewPath(plugins_path('winter/sso/controllers/auth/prevent_native'));
    });

    // Security: Disable AJAX handler
    $controller->bindEvent('ajax.beforeRunHandler', function ($handler) {
        if ($handler === 'onSubmit') {
            throw new ApplicationException("Native authentication is disabled.");
        }
    });
});
```

This both hides the form and blocks the server-side handler.

## Extension Points for Provider Plugins

Provider plugins can integrate with Winter.SSO through several mechanisms:

### 1. Socialite Provider Registration

```php
// In provider plugin's boot() method
Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
    $event->extendSocialite('microsoft', \SocialiteProviders\Microsoft\Provider::class);
});
```

### 2. Configuration Merging

```php
// Merge provider config into Winter.SSO
$this->app['config']->set(
    'winter.sso::providers.microsoft',
    $this->app['config']->get('winter.ssoprovidermicrosoft::config')
);
```

This allows provider plugins to maintain their own `config/config.php` file that gets merged into the main SSO configuration.

### 3. Custom Button/Logo

Provider plugins can include their own assets and register them through config:

```php
// In provider plugin config
return [
    'client_id' => env('MICROSOFT_CLIENT_ID'),
    'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
    'button' => [
        'label' => 'Sign in with Microsoft',
        'logoUrl' => '/plugins/winter/ssoprovidermicrosoft/assets/images/microsoft.svg',
    ],
];
```

### 4. Provider-specific Events

Provider plugins can listen to their own events:

```php
// In provider plugin's boot() method
Event::listen('winter.sso.microsoft.registered', function ($user, $ssoUser) {
    // Microsoft-specific logic
});
```

## Session and Cookie Considerations

### Same-Site Cookie Adjustment

OAuth requires cookies to be sent during redirects. The plugin automatically adjusts `session.same_site` from `strict` to `lax` when secure sessions are enabled:

```php
// plugins/winter/sso/Plugin.php:119
if (Config::get('session.secure') === true && Config::get('session.same_site') === 'strict') {
    Config::set('session.same_site', 'lax');
}
```

**Why**: With `same_site=strict`, session cookies aren't sent during OAuth callbacks, causing "Invalid state" errors.

### State Verification

Laravel Socialite stores OAuth state in the session. This state is verified on callback to prevent CSRF attacks. If the session is lost between redirect and callback, you'll see `InvalidStateException`.

## Logging System

### Log Model

**Model**: `Winter\SSO\Models\Log`
**Table**: `winter_sso_logs`

### Log Structure

```php
[
    'provider' => 'google',
    'action' => 'authenticated',
    'user_type' => 'Backend\Models\User',
    'user_id' => 1,
    'provided_id' => '1234567890',
    'provided_email' => 'user@example.com',
    'ip' => '192.168.1.1',
    'metadata' => [
        'remember' => true,
    ],
    'created_at' => '2024-01-17 10:30:00',
]
```

### Log Creation

Logs are created after successful login in `Handle::callback()`:

```php
// plugins/winter/sso/controllers/Handle.php:250
SsoLog::create([
    'provider' => $provider,
    'action' => 'authenticated',
    'user_type' => get_class($user),
    'user_id' => $user->getKey(),
    'provided_id' => $ssoUser->getId(),
    'provided_email' => $email,
    'ip' => Request::ip(),
    'metadata' => [
        'remember' => $remember,
    ],
]);
```

### Viewing Logs

**Backend URL**: Settings → Logs → SSO Logs
**Permission**: `winter.sso.view_logs`
**Controller**: `Winter\SSO\Controllers\Logs`

## Email Normalization

### Purpose

Prevent duplicate accounts when email providers allow variations (e.g., Gmail allows dots in usernames but treats them as the same account).

### Implementation

```php
// plugins/winter/sso/controllers/Handle.php:306
protected function normalizeEmail($email)
{
    [$user, $domain] = explode('@', strtolower($email));

    // Google emails can have "." anywhere but the actual account has none
    if (in_array($domain, ['gmail.com', 'googlemail.com'])) {
        $user = str_replace('.', '', $user);
    }

    return $user . '@' . $domain;
}
```

### Examples

- `John.Doe@Gmail.com` → `johndoe@gmail.com`
- `j.o.h.n@gmail.com` → `john@gmail.com`
- `john.doe@example.com` → `john.doe@example.com` (unchanged)

## Security Considerations

### 1. SSO ID Verification

Prevents account takeover by verifying the provider's ID matches on subsequent logins.

### 2. Explicit Permission System

When `require_explicit_permission` is true, users must explicitly allow connections from each provider (backend UI pending).

### 3. Unguessable Passwords

SSO-created users get 400-character random passwords, making password authentication practically impossible.

### 4. Elevated Plugin

The plugin has `$elevated = true`, allowing it to run on protected routes (necessary for auth controller extension).

### 5. CSRF Protection

Laravel Socialite uses OAuth state parameter for CSRF protection. State is stored in session and verified on callback.

## Performance Considerations

### Provider Configuration Caching

Provider configuration is read and mapped once during `boot()`, not on every request.

### Minimal Database Queries

- User lookup: 1 query (by email)
- SSO metadata: stored in JSON column, no join needed
- Log creation: 1 insert

### Asset Loading

CSS is only loaded on the signin page via `backend.auth.extendSigninView` event.

## File Structure

```
plugins/winter/sso/
├── Plugin.php                      # Main plugin class
├── controllers/
│   ├── Handle.php                  # OAuth redirect/callback
│   ├── Logs.php                    # Log viewer
│   └── auth/prevent_native/        # Views for hiding native auth
├── models/
│   └── Log.php                     # SSO log model
├── config/
│   └── config.php                  # Default configuration
├── exceptions/
│   ├── InvalidSsoIdException.php
│   └── ProviderBlockedException.php
├── assets/
│   └── images/providers/           # Provider logos (SVG)
├── views/
│   ├── providers.php               # Button container
│   └── buttons/provider.php        # Individual button template
└── docs/
    ├── architecture.md             # This file
    ├── creating-provider-plugins.md
    └── providers/                  # Provider setup guides
        └── google.md
```

## Configuration Loading Order

1. **Plugin Registration** (`Plugin::register()`)
   - Forces email login on callback routes
   - Registers Socialite service provider

2. **Plugin Boot** (`Plugin::boot()`)
   - Adjusts session.same_site if needed
   - Calls `configureProviders()` - maps config to services.*
   - Calls `extendAuthController()` - adds SSO buttons

3. **Request Handling**
   - Socialite reads from `services.{provider}`
   - Handle controller uses `winter.sso::*` config

## Future TODOs (from code comments)

1. **Backend DB Configuration** - UI for managing providers and settings
2. **User Preferences** - Allow users to manage their SSO connections
3. **Default Role Implementation** - Actually assign roles to new users
4. **Password Auth Blocking** - Enforce `allow_password_auth` flag
5. **Remember Me** - Support user-selected "remember me" checkbox
6. **Account Attachment** - Allow logged-in users to attach SSO providers
7. **Protection Against Fake Auth** - Verify trusted services
8. **Session Configuration Warning** - Dashboard widget for session config issues

## Testing Checklist

When testing SSO integration:

- [ ] Provider shows button on signin page
- [ ] Clicking button redirects to provider
- [ ] After authentication, redirected back to Winter
- [ ] Existing user can login
- [ ] New user can register (if enabled)
- [ ] SSO ID is stored on first login
- [ ] Second login with same account succeeds
- [ ] Second login with different account fails (ID mismatch)
- [ ] Log entry is created
- [ ] User metadata contains SSO data
- [ ] Events fire correctly
- [ ] Email normalization works
- [ ] Session persists correctly

## Additional Resources

- [Laravel Socialite Documentation](https://laravel.com/docs/socialite)
- [SocialiteProviders Documentation](https://socialiteproviders.com/)
- [Winter CMS Backend Authentication](https://wintercms.com/docs/backend/users)
- [Creating Provider Plugins](creating-provider-plugins.md)
