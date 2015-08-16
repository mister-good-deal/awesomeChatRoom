<?php

namespace controllers;

use \classes\entitiesManager\UserEntityManager as UserEntityManager;

class UserController
{
    public function register()
    {
        $userEntityManager = new UserEntityManager();

        echo json_encode($userEntityManager->register($_POST), true);
    }
}
