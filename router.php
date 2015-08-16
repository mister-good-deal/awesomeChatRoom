<?php

function route($route)
{
    $path            = explode('/', $route);
    $method          = array_pop($path);
    $controller      = array_pop($path);
    $deep            = count($path);
    $currentDeep     = 0;
    $route           = __DIR__ . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR .'controllers';

    while ($currentDeep < $deep) {
        $route .= DIRECTORY_SEPARATOR . $path[$currentDeep++];

        if (!is_dir($route)) {
            echo 'The route "' . $route . '" is undefined';
        }
    }

    $route .= DIRECTORY_SEPARATOR . $controller . 'Controller.php';

    if (!is_file($route)) {
        echo 'The controller "' . $controller . '" is undefined';
    }

    require_once($route);

    $controllerIntance = new $controller();
    $controllerIntance->$method();
}

if (isset($_GET['action'])) {
    route($_GET['action']);
    unset($_GET);
}
