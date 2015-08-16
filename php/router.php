<?php

include_once 'autoloader.php';

function route($route)
{
    $path            = explode('/', $route);
    $method          = array_pop($path);
    $controller      = array_pop($path) . 'Controller';
    $deep            = count($path);
    $currentDeep     = 0;
    $route           = __DIR__ . DIRECTORY_SEPARATOR .'controllers';

    while ($currentDeep < $deep) {
        $route .= DIRECTORY_SEPARATOR . $path[$currentDeep++];

        if (!is_dir($route)) {
            echo 'The route "' . $route . '" is undefined';
        }
    }

    $route .= DIRECTORY_SEPARATOR . $controller . '.php';

    if (!is_file($route)) {
        echo 'The controller "' . $controller . '" is undefined';
    }

    require_once($route);

    $controllerPath    = 'controllers' . DIRECTORY_SEPARATOR . $controller;
    $controllerIntance = new $controllerPath();
    $controllerIntance->$method();
}

if (isset($_GET['action'])) {
    route($_GET['action']);
    unset($_GET);
}
