<?php

namespace Winter\SSO\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\Backend;
use Backend\Facades\BackendAuth;
use Backend\Models\AccessLog;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Socialite;
use System\Classes\UpdateManager;
use Winter\SSO\Models\Log;
use Winter\Storm\Auth\AuthenticationException;
use Winter\Storm\Auth\Manager as AuthManager;
use Winter\Storm\Support\Facades\Config;
use Winter\Storm\Support\Facades\Flash;

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

        Event::listen('backend.auth.login', function ($user) {
            $runMigrationsOnLogin = (bool) Config::get('cms.runMigrationsOnLogin', Config::get('app.debug', false));
            if ($runMigrationsOnLogin) {
                try {
                    // Load version updates
                    UpdateManager::instance()->update();
                } catch (Exception $e) {
                    Flash::error($e->getMessage());
                }
            }
            // Log the sign in event
            AccessLog::add($user);
        });
    }

    /**
     * Processes a callback from the SSO provider
     * Redirects back to signin form on errors with Flash message.
     */
    public function callback(string $provider): RedirectResponse
    {
        try {
            if (!in_array($provider, $this->enabledProviders)) {
                throw new Exception(Lang::get('winter.sso::lang.messages.inactive_provider', ['provider' => $provider]));
            }
            if (!Request::input('code')) {
                throw new Exception(sprintf("%s: %s", Request::input('error'), Request::input('error_description')));
            }

            $result = Event::fire('backend.user.sso.beforeSignin', [$this, $provider], halt: true);
            if ($result === false) {
                throw new Exception(
                    Lang::get('winter.sso::lang.messages.signin_aborted', ['provider' => $provider])
                );
            }

            $ssoUser = Socialite::with($provider)->user();

            Event::fire('backend.user.sso.signin', [$this, $provider, $ssoUser]);

        } catch (Exception $e) {
            if ($e instanceof InvalidStateException) {
                // session.same_site must be 'lax' or 'none' if session.secure = true
                $msg = Lang::get('winter.sso::lang.messages.invalid_state', ['provider' => $provider]);
            } else {
                $msg = $e->getMessage();
            }
            return $this->redirectToSignInPage($msg);
        }

        $email = $this->normalizeEmail($ssoUser->getEmail());
        try {
            /* @TODO: Protection against service saying that root@mydomain.com is authenticated
             * - First need to know if SSO is enabled for current auth manager
             * - need to know what services are trusted to validate the user
             * - Need metadata on users to store that information
             */
            $user = $this->authManager->findUserByCredentials(['email' => $email]);

            if (Config::get('winter.sso::require_explicit_permission', false)) {
                if (!$user->getSsoValue($provider, 'allowConnection', false)) {
                    // User has to explicitly enable sso connections
                    // @TODO: Need to add 'allowConnection' setting (per provider) in Backend User Management Page.
                    throw new AuthenticationException(
                        Lang::get('winter.sso::lang.messages.connection_not_allowed', ['provider' => $provider, 'email' => $email])
                    );
                }
            }
            $ssoId = $user->getSsoValue($provider, 'id');
            if (!is_null($ssoId) && $ssoId !== $ssoUser->getId()) {
                // User has already connected via this SSO provider and the current Id must match the previous one.
                throw new AuthenticationException(
                    Lang::get('winter.sso::lang.messages.invalid_ssoid', ['provider' => $provider, 'email' => $email])
                );
            }
        } catch (AuthenticationException $e) {
            try {
                if (Config::get('winter.sso::allow_registration')) {
                    $password = Str::random(400);
                    $user = $this->authManager()->register([
                        'email' => $email,
                        'password' => $password,
                        'password_confirmation' => $password,
                        'name' => $ssoUser->getName(),
                    ]);
                    // user was registered with a random password, only allow sso logins
                    $user->setSsoValues($provider, ['allow_password_auth' => false]);
                } else {
                    throw new AuthenticationException(
                        Lang::get('winter.sso::lang.messages.user_not_found', ['user' => $email])
                    );
                }
            } catch (Exception $e) {
                return $this->redirectToSignInPage($e->getMessage() ?: get_class($e));
            }
        }

        $data = [];
        if ($ssoUser->getId() && $user->getSsoValue($provider, 'id') !== $ssoUser->getId()) {
            // @TODO: Check if request / user is allowed to associate this account to this provider's ID
            $data['id'] = $ssoUser->getId();
        }

        if ($ssoUser->token && $user->getSsoValue($provider, 'token') !== $ssoUser->token) {
            $data['token'] = $ssoUser->token;
        }

        if ($data) {
            $user->setSsoValues($provider, $data);
        }

        // Check if the user is allowed to keep a persistent session
        // @TODO: Support "null" as an option (where the user selects the remember me checkbox before logging in,
        // will probably require storing a flag in the session before redirecting them to the SSO provider login URL
        $remember = Config::get('cms.backendForceRemember', false);

        if ($user->methodExists('beforeLogin')) {
            $user->beforeLogin();
        }

        $this->authManager->login($user, $remember);

        if ($user->methodExists('afterLogin')) {
            $user->afterLogin();
        }

        Log::create([
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

        // Redirect to the intended page after successful sign in
        return Backend::redirectIntended('backend');
    }

    /**
     * Redirects the user to the authentication page of the given provider.
     * Redirects back to signin form on errors with Flash message.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (!in_array($provider, $this->enabledProviders)) {
            $msg = Lang::get('winter.sso::lang.messages.inactive_provider', ['provider' => $provider]);
            return $this->redirectToSignInPage($msg);
        }

        if ($this->authManager->getUser()) {
            // @TODO: Handle case of user explicitly attaching a SSO provider to their account
            Flash::error(Lang::get('winter.sso::lang.messages.already_logged_in'));
            return Redirect::back();
        }

        $config = Config::get('services.' . $provider, []);
        if (!isset($config['client_id'])) {
            $msg = Lang::get('winter.sso::lang.messages.misconfigured_provider', ['provider' => $provider]);
            return $this->redirectToSignInPage($msg);
        }

        try {
            $response = Socialite::with($provider)
                ->scopes($config['scopes'] ?? [])
                ->redirect();
        } catch (Exception $e) {
            return $this->redirectToSigninPage($e->getMessage());
        }
        return $response;
    }

    /**
     * Canonicalize the provided email based on domain name.
     */
    protected function normalizeEmail($email)
    {
        [$user, $domain] = explode('@', strtolower($email));

        if (in_array($domain, ['gmail.com', 'googlemail.com'])) {
            // Google emails can have "." anywhere in the username but the actual account has none.
            $user = str_replace('.', '', $user);
        }
        return $user . '@' . $domain;
    }

    protected function redirectToSigninPage(string $message = null): RedirectResponse
    {
        if ($message) {
            Flash::error($message);
        }
        return Redirect::to(Session::pull('signin_url', Backend::url('backend/auth/signin')));
    }
}
