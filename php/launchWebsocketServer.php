<?php
/**
 * Launch a websocket server instance
 *
 * @package    Launcher
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

use \classes\IniManager as Ini;
use \classes\ThrowableManager as ThrowableManager;
use \classes\websocket\ServerRequestHandler as ServerRequestHandler;
use Icicle\WebSocket\Server\Server as Server;
use Icicle\Loop;

require_once 'autoloader.php';

try {
    $params = Ini::getSectionParams('Socket');
    $server = new Server(new ServerRequestHandler());
    $server->listen($params['port'], $params['address']);
    Loop\run();
} catch (\Throwable $t) {
    $throwableManager = new ThrowableManager();
    $throwableManager->log($t);
} finally {
    exit(0);
}
