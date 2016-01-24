<?php
/**
 * Test script for the socket Server class
 *
 * @category Test
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

use \classes\websocket\Server as Server;
use \classes\websocket\ChatService as ChatService;

require_once '../autoloader.php';

try {
    $server = new Server();
} catch (Exception $e) {
} finally {
    exit(0);
}
