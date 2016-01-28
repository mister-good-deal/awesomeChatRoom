<?php
/**
 * Test script for the Orm class
 *
 * @category Test
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

use \classes\console\Orm as Orm;

require_once '../autoloader.php';

try {
    $console = new Orm();
    $console->launchConsole();
} catch (Exception $e) {
} finally {
    exit(0);
}
