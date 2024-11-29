<?php

namespace Winter\SSO\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\Backend;
use Backend\Facades\BackendAuth;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Two\InvalidStateException;
use Socialite;
use Winter\SSO\Exceptions\InvalidSsoIdException;
use Winter\SSO\Exceptions\ProviderBlockedException;
use Winter\SSO\Models\Log as SsoLog;
use Winter\Storm\Auth\AuthenticationException;
use Winter\Storm\Auth\Manager as AuthManager;
use Winter\Storm\Exception\SystemException;
use Winter\Storm\Support\Facades\Config;
use Winter\Storm\Support\Facades\Flash;
use Winter\Storm\Support\Str;

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
     * Redirects back to signin form on errors with Flash message.
     */
    public function callback(string $provider): RedirectResponse
    {
        try {
            if (!in_array($provider, $this->enabledProviders)) {
                throw new Exception(Lang::get('winter.sso::lang.errors.provider_disabled', ['provider' => $provider]));
            }
            if (!Request::input('code')) {
                throw new Exception(sprintf("%s: %s", Request::input('error'), Request::input('error_description')));
            }

            $result = Event::fire("winter.sso.$provider.authenticating", [], halt: true);
            if ($result === false) {
                throw new Exception(
                    Lang::get('winter.sso::lang.errors.authentication_aborted', ['provider' => $provider])
                );
            }

            $ssoUser = Socialite::with($provider)->user();

            Event::fire("winter.sso.$provider.authenticated", [$ssoUser]);
        } catch (Exception $e) {
            if ($e instanceof InvalidStateException) {
                $message = Lang::get('winter.sso::lang.errors.invalid_state', ['provider' => $provider]);
            } else {
                $message = $e->getMessage();
            }
            return $this->redirectToSignInPage($message);
        }

        $email = $this->normalizeEmail($ssoUser->getEmail());
        try {
            /**
             * @TODO: Protection against service saying that root@mydomain.com is authenticated
             * - First need to know if SSO is enabled for current auth manager
             * - need to know what services are trusted to validate the user
             */
            $user = $this->authManager->findUserByCredentials(['email' => $email]);

            if (Config::get('winter.sso::require_explicit_permission', false)) {
                if (!$user->getSsoValue($provider, 'allowConnection', false)) {
                    // User has to explicitly enable sso connections
                    // @TODO: Need to add 'allowConnection' setting (per provider) in Backend User Management Page.
                    throw new ProviderBlockedException(
                        Lang::get('winter.sso::lang.errors.provider_blocked', ['provider' => $provider, 'email' => $email])
                    );
                }
            }
            $ssoId = $user->getSsoValue($provider, 'id');
            if (
                !is_null($ssoId)
                && $ssoUser->getId() !== $ssoId
            ) {
                // User has already connected via this SSO provider and the current ID must match the previous one.
                throw new InvalidSsoIdException(
                    Lang::get('winter.sso::lang.errors.invalid_ssoid', ['provider' => $provider, 'email' => $email])
                );
            }
        } catch (AuthenticationException $e) {
            if (
                $e instanceof InvalidSsoIdException
                || $e instanceof ProviderBlockedException
            ) {
                return $this->redirectToSignInPage($e->getMessage());
            }

            try {
                if (Config::get('winter.sso::allow_registration', false)) {
                    $password = Str::random(400);
                    $user = $this->authManager->register(
                        credentials: [
                            'email' => $email,
                            'password' => $password,
                            'password_confirmation' => $password,
                            'name' => $ssoUser->getName(),
                        ],
                        autoLogin: true
                    );
                    // Disable password authentication for users created via SSO
                    // @TODO: actually check this value and prevent password authentication
                    $user->setSsoValues($provider, ['allow_password_auth' => false]);
                } else {
                    // If the email was not found and registration via SSO is disabled
                    throw new AuthenticationException(
                        Lang::get('winter.sso::lang.errors.email_not_found', ['email' => $email])
                    );
                }
            } catch (AuthenticationException $ex) {
                return $this->redirectToSignInPage($ex->getMessage());
            }
        }

        $data = [];
        if (
            $ssoUser->getId()
            && $user->getSsoValue($provider, 'id') !== $ssoUser->getId()
        ) {
            // @TODO: Check if request / user is allowed to associate this account to this provider's ID
            $data['id'] = $ssoUser->getId();
        }

        if (
            $ssoUser->token
            && $user->getSsoValue($provider, 'token') !== $ssoUser->token
        ) {
            $data['token'] = $ssoUser->token;
        }

        if ($data) {
            $user->setSsoValues($provider, $data);
        }

        // Check if the user is allowed to keep a persistent session
        $remember = Config::get('cms.backendForceRemember', false);

        // @TODO: Support "null" as an option (where the user selects the remember me checkbox before logging in,
        // will probably require storing a flag in the session before redirecting them to the SSO provider login URL
        if (is_null($remember)) {
            $remember = false;
        }

        if ($user->methodExists('beforeLogin')) {
            $user->beforeLogin();
        }

        $this->authManager->login($user, $remember);

        if ($user->methodExists('afterLogin')) {
            $user->afterLogin();
        }

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

        // Redirect to the intended page after successful sign in
        return Backend::redirectIntended('backend');
    }

    /**
     * Redirects the user to the authentication page of the given provider.
     * Redirects back to signin form on errors with Flash message.
     * @throws SystemException if the SSO provider is not enabled or is misconfigured.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (!in_array($provider, $this->enabledProviders)) {
            $message = Lang::get('winter.sso::lang.errors.provider_disabled', ['provider' => $provider]);
            Log::error($message);
            return $this->redirectToSignInPage($message);
        }

        if ($this->authManager->getUser()) {
            // @TODO: Handle case of user explicitly attaching a SSO provider to their account
            Flash::error(Lang::get('winter.sso::lang.errors.already_logged_in'));
            return Redirect::back();
        }

        $config = Config::get('services.' . $provider, []);
        if (!isset($config['client_id'])) {
            $message = Lang::get('winter.sso::lang.errors.missing_client_id', ['provider' => $provider]);
            Log::error($message);
            return $this->redirectToSignInPage($message);
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

        // Google emails can have "." anywhere in the username but the actual account has none.
        if (in_array($domain, ['gmail.com', 'googlemail.com'])) {
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
