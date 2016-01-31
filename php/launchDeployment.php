<?php
/**
 * Launch a deployment instance
 *
 * @category Launcher
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

use \classes\console\Deployment as Deployment;

require_once 'autoloader.php';

try {
    $console = new Deployment();
} catch (Exception $e) {
} finally {
    exit(0);
}
