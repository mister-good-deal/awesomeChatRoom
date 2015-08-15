<?php
/**
 * Test script for the socket Server class
 *
 * @category Test
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

use \classes\socket\Server as Server;

include_once '../autoloader.php';

try {
    $server = new Server();
} catch (Exception $e) {
} finally {
    exit(0);
}
