<?php

namespace Winter\SSO\Controllers;

use Backend;
use BackendAuth;
use Backend\Classes\Controller;
use Backend\Models\AccessLog;
use Config;
use Event;
use Exception;
use Flash;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
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
     * Returns the $authManager property.
     */
    public function getAuthManager() : AuthManager
    {
        return $this->authManager;
    }

    /**
     * Processes a callback from the SSO provider
     * Redirect back to signin form on errors with Flash message.
     */
    public function callback(string $provider): RedirectResponse
    {
        if (!in_array($provider, $this->enabledProviders)) {
            Flash::error(trans('winter.sso::lang.messages.inactive_provider'));
            return Backend::redirect('backend/auth/signin');
        }

        if (!Request::input('code')) {
            $error = sprintf("%s: %s", Request::input('error'), Request::input('error_description'));
            Flash::error($error);
            return Backend::redirect('backend/auth/signin');
        }

        // @TODO: Login or register the user / provide an event for plugins to handle
        // user registration themselves. Would like plugin to be able to handle frontend
        // or backend or even both. If event is used follow naming conventions from in progress
        // issues
        try {
            $ssoUser = Socialite::driver($provider)->user();
            if ($signInResult = Event::fire('winter.sso.signin', [$this, $provider, $ssoUser])) {
                // @TODO: handle event results
            }
        } catch (InvalidStateException $e) {
            Flash::error(trans('winter.sso::lang.messages.invalid_state'));
            return Backend::redirect('backend/auth/signin');
        }

        try {
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
            Event::listen('winter.sso.register', function ($controller, $provider, $ssoUser) {
                return;
                $password = Str::random(400);
                $user = $controller->getAuthManager()->register([
                    'email' => $ssoUser->getEmail(),
                    'password' => $password,
                    'password_confirmation' => $password,
                    'name' => $ssoUser->getName(),
                ]);
                $user->setSsoValues($provider, ['allow_password_auth' => false]);
                return $user;
            });
            if (Config::get('winter.sso::allow_registration')) {
                $user = Event::fire('winter.sso.register', [$this, $provider, $ssoUser]);
            }
            if (!$user) {
                Flash::error(trans('winter.sso::lang.messages.user_not_found', ['user' => $ssoUser->getEmail()]));
                return Backend::redirect('backend/auth/signin');
            }
        }

        $updates = [];
        if ($ssoUser->getId() && $user->getSsoValue($provider, 'id') !== $ssoUser->getId()) {
            // @TODO: Check if request / user is allowed to associate this account to this provider's ID
            $updates['id'] = $ssoUser->getId();
        }

        if ($ssoUser->token && $user->getSsoValue($provider, 'token') !== $ssoUser->token) {
            $updates['token'] = $ssoUser->token;
        }

        if ($updates) {
            $user->setSsoValues($provider, $updates, save:true);
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
     * Redirect back to signin form on errors with Flash message.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (!in_array($provider, $this->enabledProviders)) {
            Flash::error(trans('winter.sso::lang.messages.inactive_provider'));
            return Backend::redirect('backend/auth/signin');
        }

        if ($this->authManager->getUser()) {
            // @TODO:
            // - Handle case of user explicitly attaching a SSO provider to their account
            Flash::error(trans('winter.sso::lang.messages.already_logged_in'));
            return Backend::redirect('backend/auth/signin');
        }
        $scopes = Config::get('services.' . $provider . '.scopes', []);

        return Socialite::driver($provider)->scopes($scopes)->redirect();
    }
}
