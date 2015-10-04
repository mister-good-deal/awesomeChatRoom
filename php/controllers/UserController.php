<?php
/**
 * User controller
 *
 * @category Controller
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace controllers;

use \abstracts\AbstractController as Controller;
use \classes\entitiesManager\UserEntityManager as UserEntityManager;
use \classes\ExceptionManager as Exception;

/**
 * User controller
 *
 * @class UserController
 */
class UserController extends Controller
{
    /**
     * Register a user from $_POST values and return the result of the process as a JSON
     */
    public function register()
    {
        try {
            $userEntityManager = new UserEntityManager();

            $this->JSONresponse($userEntityManager->register($_POST));
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
            $userEntityManager = new UserEntityManager();

            $this->JSONresponse($userEntityManager->connect($_POST));
            unset($_POST);
        } catch (Exception $e) {
        }
    }
}
