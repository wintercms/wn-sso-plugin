# Creating Provider Plugins for Winter.SSO

This guide explains how to create provider plugins that extend Winter.SSO with additional OAuth providers from the [SocialiteProviders](https://socialiteproviders.com/) ecosystem.

## Why Create a Provider Plugin?

Provider plugins offer several advantages over inline configuration:

**Benefits:**
- **Self-contained**: All provider-specific code, config, and assets in one package
- **Easy distribution**: Installable via Composer by anyone
- **Dependency management**: Automatically installs the Socialite provider package
- **Clean separation**: Provider logic separate from core SSO plugin
- **Version control**: Independent versioning and updates

**When to create a plugin:**
- Provider not included in Laravel Socialite core (8 providers)
- Provider requires specific configuration or features
- You want to share the provider with the community
- Provider needs custom assets (logos, CSS)

**When to use inline configuration:**
- Quick prototyping
- One-off implementation not for distribution
- Provider is very simple with minimal config

## Provider Plugin Architecture

### Directory Structure

```
plugins/winter/ssoprovider{name}/
├── Plugin.php                      # Main plugin class
├── composer.json                   # Dependencies and metadata
├── config/
│   └── config.php                  # Provider-specific configuration
├── assets/
│   └── images/
│       └── {provider}.svg          # Provider logo
├── docs/
│   └── setup.md                    # Detailed setup instructions
└── README.md                       # Quick start guide
```

### Naming Conventions

- **Plugin name**: `Winter.SSOProvider{Name}` (PascalCase)
- **Plugin directory**: `ssoprovider{name}` (lowercase)
- **Composer package**: `yourvendor/wn-ssoprovider{name}-plugin` (lowercase, hyphenated)
- **Config key**: `{provider}` (lowercase, match Socialite driver name)

**Note**: The `winter/` vendor prefix is reserved for official Winter CMS plugins only.

**Examples for Community Plugins:**
- Microsoft: `Winter.SSOProviderMicrosoft`, `ssoprovidermicrosoft`, `acmecorp/wn-ssoprovidermicrosoft-plugin`
- Slack: `Winter.SSOProviderSlack`, `ssoproviderslack`, `johndoe/wn-ssoproviderslack-plugin`
- Okta: `Winter.SSOProviderOkta`, `ssoproviderokta`, `mycompany/wn-ssoproviderokta-plugin`

**Official First-Party Plugin Example:**
- Microsoft (official): `winter/wn-ssoprovidermicrosoft-plugin` (Winter CMS team only)

## Step-by-Step Guide

### Step 1: Find the Socialite Provider

1. Visit [SocialiteProviders.com](https://socialiteproviders.com/)
2. Search for your provider (e.g., "Microsoft", "Slack", "Auth0")
3. Note the package name (e.g., `socialiteproviders/microsoft`)
4. Read the provider's documentation for configuration requirements

### Step 2: Create Plugin Directory

```bash
mkdir -p plugins/winter/ssoprovider{name}
cd plugins/winter/ssoprovider{name}
```

Replace `{name}` with your provider name in lowercase (e.g., `microsoft`, `slack`).

### Step 3: Create Plugin.php

**Template:**

```php
<?php

namespace Winter\SSOProvider{Name};

use Event;
use System\Classes\PluginBase;

/**
 * {Provider Name} SSO Provider Plugin
 *
 * Adds {Provider Name} authentication support to Winter.SSO using Laravel Socialite.
 */
class Plugin extends PluginBase
{
    /**
     * Require Winter.SSO plugin
     */
    public $require = ['Winter.SSO'];

    /**
     * Returns information about this plugin
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => '{Provider Name} SSO Provider',
            'description' => '{Provider Name} authentication provider for Winter.SSO',
            'author'      => 'Your Name',
            'icon'        => 'icon-lock',
            'homepage'    => 'https://github.com/yourusername/wn-ssoprovider{name}-plugin',
        ];
    }

    /**
     * Boot method, called right before the request route
     */
    public function boot(): void
    {
        // Register the Socialite provider
        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('{provider}', \SocialiteProviders\{Provider}\Provider::class);
        });

        // Merge provider configuration into Winter.SSO
        $this->app['config']->set(
            'winter.sso::providers.{provider}',
            $this->app['config']->get('winter.ssoprovider{name}::config')
        );
    }
}
```

**Key points:**
- `$require = ['Winter.SSO']` ensures Winter.SSO is installed first
- Replace `{Name}`, `{Provider Name}`, `{provider}`, and `{name}` with your provider's details
- The provider key (`{provider}`) must match the Socialite driver name
- Event listener registers the Socialite provider class
- Config merging makes your config available to Winter.SSO

**Real Example (Microsoft-style implementation):**

```php
<?php

namespace Winter\SSOProviderMicrosoft;

use Event;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public $require = ['Winter.SSO'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Microsoft SSO Provider',
            'description' => 'Microsoft 365 and Azure AD authentication for Winter.SSO',
            'author'      => 'Author Name', // Use YOUR name here
            'icon'        => 'icon-windows',
            'homepage'    => 'https://github.com/yourname/wn-ssoprovidermicrosoft-plugin',
        ];
    }

    public function boot(): void
    {
        // Register Microsoft provider with Socialite
        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', \SocialiteProviders\Microsoft\Provider::class);
        });

        // Merge config
        $this->app['config']->set(
            'winter.sso::providers.microsoft',
            $this->app['config']->get('winter.ssoprovidermicrosoft::config')
        );
    }
}
```

**Note**: "Winter CMS" as author is reserved for first-party plugins only. Use your own name or organization.

### Step 4: Create composer.json

**Template:**

```json
{
    "name": "yourvendor/wn-ssoprovider{name}-plugin",
    "type": "winter-plugin",
    "description": "{Provider Name} authentication provider for Winter.SSO",
    "keywords": ["winter", "cms", "sso", "oauth", "{provider}"],
    "license": "MIT",
    "authors": [
        {
            "name": "Your Name",
            "email": "your.email@example.com"
        }
    ],
    "require": {
        "php": "^8.0.2",
        "composer/installers": "~2.0",
        "winter/wn-sso-plugin": "dev-main",
        "socialiteproviders/{provider}": "^4.0|^5.0"
    },
    "autoload": {
        "psr-4": {
            "Winter\\SSOProvider{Name}\\": ""
        }
    },
    "extra": {
        "installer-name": "ssoprovider{name}"
    }
}
```

**Key points:**
- Package name: `yourvendor/wn-ssoprovider{name}-plugin` (use your Composer vendor name)
- **Note**: The `winter/` vendor prefix is reserved for official first-party plugins
- Type: `winter-plugin` (required for Winter CMS)
- Dependency on `winter/wn-sso-plugin` with version `dev-main`
- Dependency on the specific Socialite provider package
- Check provider's package page for correct version constraint
- PSR-4 autoload namespace must match your plugin namespace
- `installer-name` defines the directory name under `plugins/winter/`

**Real Example (Microsoft-style implementation):**

```json
{
    "name": "authorname/wn-ssoprovidermicrosoft-plugin",
    "type": "winter-plugin",
    "description": "Microsoft 365 and Azure AD authentication provider for Winter.SSO",
    "keywords": ["winter", "cms", "sso", "oauth", "microsoft", "azure", "office365"],
    "license": "MIT",
    "authors": [
        {
            "name": "Author Name",
            "email": "author@example.com"
        }
    ],
    "require": {
        "php": "^8.0.2",
        "composer/installers": "~2.0",
        "winter/wn-sso-plugin": "dev-main",
        "socialiteproviders/microsoft": "^4.3"
    },
    "autoload": {
        "psr-4": {
            "Winter\\SSOProviderMicrosoft\\": ""
        }
    },
    "extra": {
        "installer-name": "ssoprovidermicrosoft"
    }
}
```

**Note**: Use your own Composer vendor name (e.g., `yourname/wn-ssoprovidermicrosoft-plugin`). The `winter/` vendor prefix is reserved for first-party plugins.

### Step 5: Create config/config.php

Configuration varies by provider. Check the Socialite provider's documentation.

**Basic Template:**

```php
<?php

return [
    /**
     * OAuth Client ID from provider
     */
    'client_id' => env('{PROVIDER}_CLIENT_ID'),

    /**
     * OAuth Client Secret from provider
     */
    'client_secret' => env('{PROVIDER}_CLIENT_SECRET'),

    /**
     * HTTP client options (Guzzle)
     * Useful for proxies, timeouts, etc.
     */
    'guzzle' => [],

    /**
     * Button customization for login page
     * IMPORTANT: Must specify logoUrl pointing to provider plugin's assets
     */
    'button' => [
        'label' => 'Sign in with {Provider Name}',
        'logoUrl' => '/plugins/winter/ssoprovider{name}/assets/images/{provider}.svg',
    ],
];
```

**Advanced Example (Microsoft with tenant support):**

```php
<?php

return [
    'client_id' => env('MICROSOFT_CLIENT_ID'),
    'client_secret' => env('MICROSOFT_CLIENT_SECRET'),

    /**
     * Tenant configuration:
     * - 'common': Any Microsoft account (work, school, or personal)
     * - 'organizations': Work or school accounts only
     * - 'consumers': Personal Microsoft accounts only
     * - Specific tenant ID: Single tenant only
     */
    'tenant' => env('MICROSOFT_TENANT', 'common'),

    /**
     * Include tenant information in user object
     */
    'include_tenant_info' => env('MICROSOFT_INCLUDE_TENANT_INFO', false),

    /**
     * Include user avatar
     */
    'include_avatar' => env('MICROSOFT_INCLUDE_AVATAR', true),

    /**
     * Additional OAuth scopes
     * Default scopes are usually sufficient
     */
    'scopes' => env('MICROSOFT_SCOPES', ''),

    'guzzle' => [],

    'button' => [
        'label' => 'Sign in with Microsoft',
        'logoUrl' => '/plugins/winter/ssoprovidermicrosoft/assets/images/microsoft.svg',
    ],
];
```

**Configuration Tips:**
- Use environment variables for secrets
- Provide sensible defaults where possible
- Document all configuration options with comments
- Include provider-specific settings (e.g., tenant, domain, API version)
- **Always specify logoUrl** pointing to your provider plugin's assets directory

### Step 6: Add Provider Logo

1. Create directory: `assets/images/`
2. Add SVG logo: `assets/images/{provider}.svg`
3. Logo should be:
   - SVG format (scalable)
   - Square or nearly square aspect ratio
   - Simple, recognizable icon
   - Reasonable file size (< 50KB)
   - Work on light backgrounds

**Where to find logos:**
- Provider's brand assets / press kit
- [SimpleIcons.org](https://simpleicons.org/) (look for official logos)
- Provider's developer documentation

**Example:**
```bash
curl -o assets/images/microsoft.svg https://simpleicons.org/icons/microsoft.svg
```

**Important:** After adding the logo, you must reference it in your `config/config.php` button configuration:
```php
'button' => [
    'label' => 'Sign in with {Provider Name}',
    'logoUrl' => '/plugins/winter/ssoprovider{name}/assets/images/{provider}.svg',
],
```

The logo path must point to your provider plugin's assets directory, not the core Winter.SSO plugin.

### Step 7: Create README.md

**Template:**

```markdown
# Winter.SSOProvider{Name}

{Provider Name} authentication provider for [Winter.SSO](https://github.com/wintercms/wn-sso-plugin).

## Installation

```bash
composer require yourvendor/wn-ssoprovider{name}-plugin
```

## Requirements

- Winter CMS v1.2+
- Winter.SSO plugin
- {Provider Name} OAuth application

## Quick Start

### 1. Create OAuth Application

[Brief instructions or link to provider's OAuth setup page]

### 2. Configure Environment

Add to your `.env` file:

```env
{PROVIDER}_CLIENT_ID=your_client_id
{PROVIDER}_CLIENT_SECRET=your_client_secret
```

### 3. Enable Provider

Edit `config/winter/sso/config.php`:

```php
return [
    'enabled_providers' => [
        '{provider}',
    ],
];
```

### 4. Test

Visit `/backend/auth/signin` and click "Sign in with {Provider Name}"!

## Configuration

See [Setup Guide](docs/setup.md) for detailed configuration options and provider-specific features.

## Documentation

- [Detailed Setup Guide](docs/setup.md)
- [Winter.SSO Documentation](https://github.com/wintercms/wn-sso-plugin/blob/main/README.md)
- [{Provider Name} OAuth Documentation](link-to-provider-docs)

## License

MIT License. See [LICENSE](LICENSE) for details.
```

### Step 8: Create docs/setup.md

This should be a comprehensive, step-by-step guide similar to the Google setup guide in Winter.SSO.

**Structure:**
1. **Overview** - What the provider offers
2. **Prerequisites** - What you need before starting
3. **OAuth Application Setup** - Detailed steps with screenshots
   - Creating the application
   - Configuring redirect URLs
   - Getting credentials
   - Setting permissions/scopes
4. **Winter CMS Configuration** - Environment variables and config files
5. **Testing** - How to verify it works
6. **Optional Features** - Advanced configuration (scopes, additional data, etc.)
7. **Troubleshooting** - Common issues and solutions

See `plugins/winter/sso/docs/providers/google.md` for a real example.

### Step 9: Test Your Plugin

1. **Install locally**:
   ```bash
   composer require yourvendor/wn-ssoprovider{name}-plugin
   php artisan winter:up
   ```

2. **Enable provider**:
   ```php
   // config/winter/sso/config.php
   'enabled_providers' => ['{provider}'],
   ```

3. **Set environment variables**:
   ```env
   {PROVIDER}_CLIENT_ID=test_id
   {PROVIDER}_CLIENT_SECRET=test_secret
   ```

4. **Visit login page**: `/backend/auth/signin`

5. **Check for**:
   - Provider button appears
   - Logo displays correctly
   - Clicking redirects to provider
   - After auth, redirected back to Winter
   - User can login/register
   - Events fire correctly

### Step 10: Publish Your Plugin

1. **Create repository**: GitHub, GitLab, etc.
2. **Tag version**: `v1.0.0`
3. **Submit to Packagist**: [packagist.org](https://packagist.org/packages/submit)
4. **Submit to Winter Marketplace**: [wintercms.com/marketplace](https://wintercms.com/marketplace)
5. **Announce**: Winter CMS Discord, forum, etc.

## Advanced Features

### Custom Button Views

Override the button template:

```php
// config/config.php
'button' => [
    'view' => 'winter.ssoprovider{name}::button',
    'label' => 'Custom Label',
    'logoUrl' => '/plugins/winter/ssoprovider{name}/assets/images/{provider}.svg',
],
```

**Note:** Even with a custom view, you should still specify `logoUrl` so the `$logoUrl` variable is available in your view template.

Then create `views/button.php`:

```php
<button
    type="button"
    class="btn btn-default sso-button sso-{provider}"
    onclick="window.location.href='<?= $url ?>'"
>
    <img src="<?= $logoUrl ?>" alt="<?= $logoAlt ?>">
    <?= e($label) ?>
</button>
```

### Provider-Specific Events

Listen to events in your plugin:

```php
// Plugin.php::boot()
Event::listen('winter.sso.{provider}.registered', function ($user, $ssoUser) {
    // Provider-specific logic
    $user->fill([
        'custom_field' => $ssoUser->user['custom_data'],
    ]);
    $user->save();
});
```

### Additional Configuration Options

Expose provider-specific features:

```php
// config/config.php
'api_version' => env('{PROVIDER}_API_VERSION', 'v2'),
'domain' => env('{PROVIDER}_DOMAIN'),
'include_profile_photo' => env('{PROVIDER}_INCLUDE_PHOTO', true),
'scopes' => explode(',', env('{PROVIDER}_SCOPES', 'user:email')),
```

### Migrations

If your provider needs additional database fields:

```php
// updates/create_provider_data_table.php
public function up()
{
    Schema::create('winter_ssoprovider{name}_data', function ($table) {
        $table->increments('id');
        $table->integer('user_id')->unsigned();
        $table->string('provider_user_id');
        $table->text('provider_data')->nullable();
        $table->timestamps();
    });
}
```

## Reference Implementation

See **Winter.SSOProviderMicrosoft** as a complete reference implementation:

```
plugins/winter/ssoprovidermicrosoft/
├── Plugin.php
├── composer.json
├── config/config.php
├── assets/images/microsoft.svg
├── docs/setup.md
└── README.md
```

Study this plugin to understand:
- Multi-tenant support (common vs. specific tenant ID)
- Optional features (tenant info, avatar, roles)
- Environment variable usage
- Comprehensive documentation

## Testing Checklist

Before publishing your provider plugin:

- [ ] Plugin namespace and naming follow conventions
- [ ] composer.json is valid and complete
- [ ] Socialite provider registers correctly
- [ ] Configuration merges into Winter.SSO
- [ ] Provider logo exists and displays
- [ ] README.md has clear quick start
- [ ] docs/setup.md has detailed instructions
- [ ] OAuth flow completes successfully
- [ ] Existing users can login
- [ ] New users can register (if enabled)
- [ ] Events fire correctly
- [ ] No console errors or PHP warnings
- [ ] Works with `prevent_native_auth` enabled
- [ ] Documentation mentions redirect URL format
- [ ] All environment variables documented
- [ ] Code follows Winter CMS coding standards

## Troubleshooting Plugin Development

### "Class not found" errors

- Check PSR-4 namespace in composer.json matches your class namespace
- Run `composer dump-autoload`
- Clear cache: `php artisan cache:clear`

### Provider button doesn't appear

- Ensure provider is in `enabled_providers` array
- Check `client_id` is set in config
- Verify config merging in Plugin.php is correct
- Check browser console for JavaScript errors

### "Invalid state" errors during OAuth

- Ensure sessions are configured correctly
- Check session.same_site is 'lax' or 'none'
- Clear browser cookies and test in incognito
- Verify redirect URL matches exactly in provider settings

### Configuration not loading

- Config key must match: `winter.ssoprovider{name}::config`
- Config merge must happen in `boot()`, not `register()`
- Check config file returns an array
- Test: `Config::get('winter.ssoprovider{name}::config')`

## Best Practices

1. **Follow conventions**: Use the naming patterns outlined in this guide
2. **Document thoroughly**: README + detailed setup guide minimum
3. **Test with real OAuth app**: Don't rely on assumptions
4. **Include troubleshooting**: Document common issues you encountered
5. **Keep it simple**: Only add features the provider actually supports
6. **Use environment variables**: Never hardcode credentials
7. **Provide defaults**: Make configuration optional where possible
8. **Link to official docs**: Point users to the provider's OAuth documentation
9. **Version appropriately**: Use semantic versioning (semver.org)
10. **Maintain compatibility**: Test against Winter.SSO updates

## Resources

- [Winter CMS Plugin Development](https://wintercms.com/docs/plugin/registration)
- [Laravel Socialite Documentation](https://laravel.com/docs/socialite)
- [SocialiteProviders Documentation](https://socialiteproviders.com/)
- [Winter.SSO Architecture](architecture.md)
- [Composer Documentation](https://getcomposer.org/doc/)
- [OAuth 2.0 Overview](https://oauth.net/2/)

## Getting Help

- [Winter CMS Discord](https://discord.gg/wintercms) - #plugins channel
- [Winter CMS Forum](https://wintercms.com/forum)
- [Winter.SSO Issues](https://github.com/wintercms/wn-sso-plugin/issues)
- [SocialiteProviders Issues](https://github.com/SocialiteProviders/Providers/issues)

## Contributing

If you create a provider plugin for a popular service, consider contributing it to the official Winter CMS organization for wider distribution and maintenance.

Contact the Winter CMS team via Discord or GitHub to discuss contributions.
