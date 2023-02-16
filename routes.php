<?php

// use Config;
use Laravel\Socialite\Facades\Socialite;
use System\Classes\UpdateManager;
use Backend\Models\AccessLog;
use Winter\SSO\Models\Log;
// use Backend;
// use BackendAuth;
// use Flash;
// use Redirect;

$enabledProviders = Config::get('winter.sso::enabled_providers', []);

Route::get('winter.sso/{provider}/redirect', function (string $provider) use ($enabledProviders) {
    if (!in_array($provider, $enabledProviders)) {
        abort(404);
    }

    if (BackendAuth::getUser()) {
        // @TODO:
        // - Handle case of user explicitly attaching a SSO provider to their account
        // - Localization
        Flash::error("You are already logged in. Please log out first.");
        return Redirect::back();
    }

    return Socialite::driver($provider)->redirect();
})->name('winter.sso.redirect')->middleware('web');

Route::get('winter.sso/{provider}/callback', function (string $provider) use ($enabledProviders) {
    if (!in_array($provider, $enabledProviders)) {
        abort(404);
    }

    // @TODO: Login or register the user / provide an event for plugins to handle
    // user registration themselves. Would like plugin to be able to handle frontend
    // or backend or even both. If event is used follow naming conventions from in progress
    // issues

    $socialUser = Socialite::driver($provider)->user();

    try {
        // @TODO: Protection against service saying that root@mydomain.com is authenticated
        // - First need to know if SSO is enabled for current auth manager
        // - need to know if the user has to explicitly enable it for their account or not,
        // - need to know what services are trusted to validate the user
        // - need to know if the user has already connected via SSO and if so what is the ID
        // for the current service because that MUST match the returned result here.
        // - Need metadata on users to store that information
        $backendUser = BackendAuth::findUserByCredentials(['email' => $socialUser->getEmail()]);
    } catch (\Winter\Storm\Auth\AuthenticationException $e) {
        $backendUser = null;
    }

    if (!$backendUser) {
        // $password = Str::random(400);
        // $backendUser = BackendAuth::register([
        //     'email' => $socialUser->getEmail(),
        //     'password' => $password,
        //     'password_confirmation' => $password,
        //     'name' => $socialUser->getName(),
        // ]);
        // $backendUser->setSsoConfig('allow_password_auth', false);
        // @TODO: Event here for registering user if desired, default fallback abort behaviour
        abort(403, 'User not found');
    }

    if (
        $socialUser->getId()
        && $backendUser->getSsoId($provider) !== $socialUser->getId()
    ) {
        // @TODO: Check if request / user is allowed to associate this account to this provider's ID
        $backendUser->setSsoId($provider, $socialUser->getId());
        $backendUser->save();
    }

    // Check if the user is allowed to keep a persistent session
    if (is_null($remember = Config::get('cms.backendForceRemember', false))) {
        $remember = false;
        // @TODO: Get this from the request
        // $remember = (bool) post('remember');
    }

    BackendAuth::login($backendUser, $remember);

    Log::create([
        'provider' => $provider,
        'action' => 'authenticated',
        'user_type' => get_class($backendUser),
        'user_id' => $backendUser->getKey(),
        'provided_id' => $socialUser->getId(),
        'provided_email' => $socialUser->getEmail(),
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
    AccessLog::add($backendUser);

    // Redirect to the intended page after successful sign in
    return Backend::redirectIntended('backend');
})->name('winter.sso.callback')->middleware('web');
