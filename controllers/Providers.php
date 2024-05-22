<?php namespace Winter\SSO\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Providers Backend Controller
 */
class Providers extends Controller
{
    /**
     * @var array Behaviors that are implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $bodyClass = 'compact-container';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Winter.SSO', 'sso', 'providers');
    }
}
