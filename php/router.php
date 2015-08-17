<?php

include_once 'autoloader.php';

function route($route)
{
    if (!is_string($route)) {
        die('The route must be a string');
    }

    echo $route . PHP_EOL;
    
    $path            = explode('/', $route);
    $method          = array_pop($path);
    $controller      = ucfirst(array_pop($path)) . 'Controller';
    $deep            = count($path);
    $currentDeep     = 0;
    $route           = __DIR__ . DIRECTORY_SEPARATOR .'controllers';

    while ($currentDeep < $deep) {
        $route .= DIRECTORY_SEPARATOR . $path[$currentDeep++];

        if (!is_dir($route)) {
            die('The route "' . $route . '" is undefined');
        }
    }

    $route .= DIRECTORY_SEPARATOR . $controller . '.php';

    if (!is_file($route)) {
        die('The controller "' . $controller . '" is undefined');
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
