# Single Sign On Plugin

[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/wintercms/wn-sso-plugin/blob/main/LICENSE)

Adds support for OAuth-based Single Sign On (SSO) to the Winter CMS backend module through the use of [Laravel Socialite](https://github.com/laravel/socialite).

Supports:
- SSO authentication with core providers from Socialite
- Easy integration with all community managed [Socialite Providers](https://socialiteproviders.com/).

## Installation

This plugin is available for installation via [Composer](http://getcomposer.org/).

```bash
composer require winter/wn-sso-plugin
```

After installing the plugin you will need to run the migrations and (if you are using a [public folder](https://wintercms.com/docs/develop/docs/setup/configuration#using-a-public-folder)) [republish your public directory](https://wintercms.com/docs/develop/docs/console/setup-maintenance#mirror-public-files).

```bash
php artisan migrate
```
