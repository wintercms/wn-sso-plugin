<?php

namespace Winter\SSO\Controllers;

use Backend;
use Backend\Classes\Controller;
use Backend\Models\AccessLog;
use BackendAuth;
use Config;
use Exception;
use Flash;
use Illuminate\Http\RedirectResponse;
use Redirect;
use Request;
use Socialite;
use System\Classes\UpdateManager;
use Winter\SSO\Models\Log;
use Winter\Storm\Auth\AuthenticationException;
use Winter\Storm\Auth\Manager as AuthManager;

/**
 * Handle SSO Backend Controller
 */
class Handle extends Controller
{
    /**
     * Defines a collection of actions available without authentication.
     */
    protected $publicActions = [
        'callback',
        'redirect',
    ];

    /**
     * List of enabled providers.
     */
    protected array $enabledProviders = [];

    /**
     * Instance of the auth manager.
     */
    protected ?AuthManager $authManager = null;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = BackendAuth::instance();

        $this->enabledProviders = Config::get('winter.sso::enabled_providers', []);
    }

    /**
     * Processes a callback from the SSO provider
     * @throws HttpException if the provider is not enabled
     * @throws HttpException if the user cannot be found
     */
    public function callback(string $provider): RedirectResponse
    {
        if (!in_array($provider, $this->enabledProviders)) {
            abort(404);
        }

        // @TODO: Login or register the user / provide an event for plugins to handle
        // user registration themselves. Would like plugin to be able to handle frontend
        // or backend or even both. If event is used follow naming conventions from in progress
        // issues

        try {
            $ssoUser = Socialite::driver($provider)->user();

            // @TODO: Protection against service saying that root@mydomain.com is authenticated
            // - First need to know if SSO is enabled for current auth manager
            // - need to know if the user has to explicitly enable it for their account or not,
            // - need to know what services are trusted to validate the user
            // - need to know if the user has already connected via SSO and if so what is the ID
            // for the current service because that MUST match the returned result here.
            // - Need metadata on users to store that information
            $user = $this->authManager->findUserByCredentials(['email' => $ssoUser->getEmail()]);
        } catch (AuthenticationException $e) {
            $user = null;
        }

        if (!$user) {
            // $password = Str::random(400);
            // $user = $this->authManager->register([
            //     'email' => $ssoUser->getEmail(),
            //     'password' => $password,
            //     'password_confirmation' => $password,
            //     'name' => $ssoUser->getName(),
            // ]);
            // $user->setSsoConfig('allow_password_auth', false);
            // @TODO: Event here for registering user if desired, default fallback abort behaviour
            abort(403, 'User not found');
        }

        if (
            $ssoUser->getId()
            && $user->getSsoId($provider) !== $ssoUser->getId()
        ) {
            // @TODO: Check if request / user is allowed to associate this account to this provider's ID
            $user->setSsoId($provider, $ssoUser->getId());
            $user->save();
        }

        // Check if the user is allowed to keep a persistent session
        if (is_null($remember = Config::get('cms.backendForceRemember', false))) {
            $remember = false;
            // @TODO: Get this from the request
            // $remember = (bool) post('remember');
        }

        $this->authManager->login($user, $remember);

        Log::create([
            'provider' => $provider,
            'action' => 'authenticated',
            'user_type' => get_class($user),
            'user_id' => $user->getKey(),
            'provided_id' => $ssoUser->getId(),
            'provided_email' => $ssoUser->getEmail(),
            'ip' => Request::ip(),
            'metadata' => [
                'remember' => $remember,
            ],
        ]);

        // @TODO: Handle this via an event listener on the backend.auth.login event
        // and ensure that the event is fired by the AuthManager
        $runMigrationsOnLogin = (bool) Config::get('cms.runMigrationsOnLogin', Config::get('app.debug', false));
        if ($runMigrationsOnLogin) {
            try {
                // Load version updates
                UpdateManager::instance()->update();
            } catch (Exception $ex) {
                Flash::error($ex->getMessage());
            }
        }

        // @TODO: Also handle via event listener
        // Log the sign in event
        AccessLog::add($user);

        // Redirect to the intended page after successful sign in
        return Backend::redirectIntended('backend');
    }

    /**
     * Redirects the user to the authentication page of the given provider.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (!in_array($provider, $this->enabledProviders)) {
            abort(404);
        }

        if ($this->authManager->getUser()) {
            // @TODO:
            // - Handle case of user explicitly attaching a SSO provider to their account
            // - Localization
            Flash::error("You are already logged in. Please log out first.");
            return Redirect::back();
        }
        $scopes = Config::get('services.' . $provider . '.scopes', []);

        return Socialite::driver($provider)->scopes($scopes)->redirect();
    }
}
