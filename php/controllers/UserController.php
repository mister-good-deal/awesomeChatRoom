<?php
/**
 * User controller
 *
 * @package    Controller
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace controllers;

use \abstracts\AbstractController as Controller;
use \classes\managers\UserManager as UserManager;
use \classes\ExceptionManager as Exception;

/**
 * User controller
 */
class UserController extends Controller
{
    /**
     * Register a user from $_POST values and return the result of the process as a JSON
     */
    public function register()
    {
        try {
            $userManager = new UserManager();

            $this->JSONresponse($userManager->register($_POST));
            unset($_POST);
        } catch (Exception $e) {
        }
    }

    /**
     * Connect a user from $_POST values and return the result of the process as a JSON
     */
    public function connect()
    {
        try {
            $userManager = new UserManager();

            $this->JSONresponse($userManager->connect($_POST));
            unset($_POST);
        } catch (Exception $e) {
        }
    }
}
