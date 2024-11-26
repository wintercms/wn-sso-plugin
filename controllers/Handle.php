<?php

namespace Winter\SSO\Controllers;

use Backend\Facades\Backend;
use Backend\Classes\Controller;
use Backend\Models\AccessLog;
use Backend\Facades\BackendAuth;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
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
    }

    /**
     * Processes a callback from the SSO provider
     * @throws HttpException if the provider is not enabled
     * @throws HttpException if the user cannot be found
     */
    public function callback(string $provider): RedirectResponse
    {
        if (!in_array($provider, $this->enabledProviders)) {
            return $this->redirectToSigninPage("The {$provider} SSO provider is not enabled");
        }

        // @TODO: Login or register the user / provide an event for plugins to handle
        // user registration themselves. Would like plugin to be able to handle frontend
        // or backend or even both. If event is used follow naming conventions from in progress
        // issues

        if (!Request::input('code')) {
            // ref. https://datatracker.ietf.org/doc/html/rfc6749#section-4.1.2.1
            $message = 'Error: no access token was returned by provider (' . $provider . ')';
            if ($error = Request::input('error')) {
                $message = $provider . ' SSO error: ' . $error;
                if ($errorDescription = Request::input('error_description')) {
                    $message .= ' (' . $errorDescription . ')';
                }
            }
            return $this->redirectToSigninPage($message);
        }

        $ssoUser = Socialite::with($provider)->user();

        try {
            // @TODO: Protection against service saying that root@mydomain.com is authenticated
            // - First need to know if SSO is enabled for current auth manager
            // - need to know if the user has to explicitly enable it for their account or not,
            // - need to know what services are trusted to validate the user
            // - need to know if the user has already connected via SSO and if so what is the ID
            // for the current service because that MUST match the returned result here.
            // - Need metadata on users to store that information
            $email = $this->normalizeEmail($ssoUser->getEmail());
            $user = $this->authManager->findUserByCredentials(['email' => $email]);
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
            return $this->redirectToSigninPage("An account for $email could not be found.");
        }

        if (
            $ssoUser->getId()
            && $user->getSsoValue($provider, 'id') !== $ssoUser->getId()
        ) {
            // @TODO: Check if request / user is allowed to associate this account to this provider's ID
            $user->setSsoValues($provider, ['id' => $ssoUser->getId()]);
            $user->save();
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
            } catch (Exception $e) {
                Flash::error($e->getMessage());
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
            return $this->redirectToSigninPage("The {$provider} SSO provider is not enabled");
        }

        if ($this->authManager->getUser()) {
            // @TODO:
            // - Handle case of user explicitly attaching a SSO provider to their account
            // - Localization
            Flash::error('You are already logged in. Please log out first.');
            return Redirect::back();
        }

        $config = Config::get('services.' . $provider, []);

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
