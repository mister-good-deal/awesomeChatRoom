<?php
/**
 * Test script for the Console class
 *
 * @category Test
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

use \classes\console\Console as Console;

require_once '../autoloader.php';

try {
    $console = new Console();
    $console->launchConsole();
} catch (Exception $e) {
} finally {
    exit(0);
}
