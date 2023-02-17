<?php

namespace Winter\SSO;

use Backend;
use Backend\Models\User;
use Backend\Models\UserRole;
use Config;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteServiceProvider;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;

/**
 * SSO Plugin Information File
 * @TODO:
 * - Add backend configuration for providers
 */
class Plugin extends PluginBase
{
    /**
     * Flag that allows this plugin to run on protected routes, required to extend the auth controller.
     * @NOTE: Not required until we actually implement extension of the auth controller
     */
    // public $elevated = true;

    /**
     * Returns information about this plugin.
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'winter.sso::lang.plugin.name',
            'description' => 'winter.sso::lang.plugin.description',
            'author'      => 'Winter',
            'icon'        => 'icon-leaf',
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
        User::$loginAttribute = 'email';
        User::extend(function ($model) {
            $model->addDynamicMethod('getSsoId', function (string $provider) use ($model) {
                return $model->metadata['winter.sso'][$provider]['id'] ?? null;
            });
            $model->addDynamicMethod('setSsoId', function (string $provider, string $id) use ($model) {
                $metadata = $model->metadata ?? [];
                $metadata['winter.sso'][$provider]['id'] = $id;
                $model->metadata = $metadata;
            });
        });
    }

    /**
     * Boot method, called right before the request route.
     */
    public function boot(): void
    {
        $this->configureProviders();
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
                'redirect' => route('winter.sso.callback', ['provider' => $provider]),
            ], $config);

            // Set the service configuration for the provider
            Config::set("services.{$provider}", $config);
        }
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
