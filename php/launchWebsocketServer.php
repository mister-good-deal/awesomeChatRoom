<?php
/**
 * Launch a websocket server instance
 *
 * @package    Launcher
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

use \classes\websocket\Server as Server;

require_once 'autoloader.php';

try {
    $server = new Server();
} catch (Exception $e) {
} finally {
    exit(0);
}
