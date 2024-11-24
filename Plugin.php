<?php

namespace Winter\SSO;

use Backend;
use Backend\Models\User;
use Backend\Models\UserRole;
use Config;
use Event;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteServiceProvider;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;
use View;

/**
 * SSO Plugin Information File
 * @TODO:
 * - Add backend DB configuration for providers (and all settings)
 * - Add backend configuration for the user to set their SSO integrations
 */
class Plugin extends PluginBase
{
    /**
     * Flag that allows this plugin to run on protected routes, required to extend the auth controller.
     */
    public $elevated = true;

    /**
     * Returns information about this plugin.
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'winter.sso::lang.plugin.name',
            'description' => 'winter.sso::lang.plugin.description',
            'author'      => 'Winter CMS',
            'icon'        => 'icon-lock',
        ];
    }

    /**
     * Returns the permissions provided by this plugin
     */
    public function registerPermissions(): array
    {
        return [
            'winter.sso.view_logs' => [
                'label' => 'winter.sso::lang.permissions.view_logs',
                'tab' => 'winter.sso::lang.plugin.name',
                'roles' => [UserRole::CODE_DEVELOPER],
            ],
            'winter.sso.view_providers' => [
                'label' => 'winter.sso::lang.permissions.view_providers',
                'tab' => 'winter.sso::lang.plugin.name',
                'roles' => [UserRole::CODE_DEVELOPER],
            ],
        ];
    }

    /**
     * Returns the settings provided by this plugin
     */
    public function registerSettings(): array
    {
        return [
            'logs' => [
                'label'       => 'winter.sso::lang.models.log.label_plural',
                'description' => 'winter.sso::lang.models.log.menu_description',
                'icon'        => 'icon-key',
                'url'         => Backend::url('winter/sso/logs'),
                'permissions' => ['winter.sso.view_logs'],
                'category'    => SettingsManager::CATEGORY_LOGS,
            ],
            'providers' => [
                'label'       => 'winter.sso::lang.models.provider.label_plural',
                'description' => 'winter.sso::lang.models.provider.menu_description',
                'icon'        => 'icon-openid',
                'url'         => Backend::url('winter/sso/providers'),
                'permissions' => ['winter.sso.view_providers'],
                'category'    => SettingsManager::CATEGORY_SYSTEM,
            ],
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     */
    public function register(): void
    {
        $this->forceEmailLogin();
        $this->registerSocialite();
    }

    /**
     * Enforce the use of email addresses as the login attribute.
     */
    protected function forceEmailLogin(): void
    {
        // Force email login attribute on SSO callback route
        if (str_starts_with(Request::url(), Backend::url('winter/sso/handle/callback/'))) {
            User::$loginAttribute = 'email';
        }

        User::extend(function ($model) {
            $model->addDynamicMethod('getSsoValue', function (string $provider, mixed $key, $default = null) use ($model) {
                return $model->metadata['winter.sso'][$provider][$key] ?? $default;
            });
            $model->addDynamicMethod('setSsoValues', function (string $provider, array $values, bool $save = false) use ($model) {
                $metadata = is_array($model->metadata) ? $model->metadata : [];
                foreach ($values as $key => $value) {
                    $metadata['winter.sso'][$provider][$key] = $value;
                }
                $model->metadata = $metadata;
                if ($save) {
                    $model->save();
                }
            });
        });
    }

    /**
     * Boot method, called right before the request route.
     */
    public function boot(): void
    {
        // Secure sessions with same_site set to strict prevents Socialite's SSO session data from being saved
        // @TODO: Warn the user about this, perhaps in the system configuration warnings dashboard widget
        if (Config::get('session.secure') === true && Config::get('session.same_site') === 'strict') {
            Config::set('session.same_site', 'lax');
        }
        $this->configureProviders();
        $this->extendAuthController();
    }

    /**
     * Ensure the configuration for the providers is set.
     */
    protected function configureProviders(): void
    {
        // Populate the services configuration with the socialite providers
        $services = Config::get('services', []);
        $providers = Config::get('winter.sso::providers', []);
        $enabledProviders = Config::get('winter.sso::enabled_providers', []);
        foreach ($providers as $provider => $config) {
            if (
                !in_array($provider, $enabledProviders)
                || empty($config['client_id'])
                || !empty($services[$provider]['client_id'])
            ) {
                continue;
            }

            $config = array_merge([
                'redirect' => Backend::url('winter/sso/handle/callback/' . $provider),
            ], $config);

            // Set the service configuration for the provider
            Config::set("services.{$provider}", $config);
        }
    }

    /**
     * Extend the auth controller to add the SSO login buttons.
     */
    protected function extendAuthController(): void
    {
        // Extend the signin view to add the SSO buttons for the enabled providers
        Event::listen('backend.auth.extendSigninView', function ($controller) {
            $controller->addCss('/plugins/winter/sso/assets/dist/css/sso.css', 'Winter.SSO');

            if ($view = View::make("winter.sso::providers", ['providers' => Config::get('winter.sso::enabled_providers', [])])) {
                // save signin_url to redirect
                Session::put('signin_url', Request::url());
                echo $view;
            }
        });
    }

    /**
     * Register the Socialite service provider.
     */
    protected function registerSocialite(): void
    {
        $this->app->register(SocialiteServiceProvider::class);
        $this->app->alias('Socialite', Socialite::class);
    }
}
