<?php

namespace Winter\SSO\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Logs Backend Controller
 */
class Logs extends Controller
{
    /**
     * @var array Behaviors that are implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $requiredPermissions = ['winter.sso.view_logs'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Winter.SSO', 'sso', 'logs');
    }
}
