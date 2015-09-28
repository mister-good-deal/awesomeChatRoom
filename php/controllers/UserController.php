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
        $userEntityManager = new UserEntityManager();

        $this->JSONresponse($userEntityManager->register($_POST));
        unset($_POST);
    }

    /**
     * Connect a user from $_POST values and return the result of the process as a JSON
     */
    public function connect()
    {
        $userEntityManager = new UserEntityManager();

        $this->JSONresponse($userEntityManager->connect($_POST));
        unset($_POST);
    }
}
