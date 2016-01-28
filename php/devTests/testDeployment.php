<?php
/**
 * Test script for the Deployment class
 *
 * @category Test
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

use \classes\console\Deployment as Deployment;

require_once '../autoloader.php';

try {
    $console = new Deployment();
    $console->launchConsole();
} catch (Exception $e) {
} finally {
    exit(0);
}
