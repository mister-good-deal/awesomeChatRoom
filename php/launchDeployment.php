<?php
/**
 * Launch a deployment instance
 *
 * @package    Launcher
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

declare(strict_types=1);

use \classes\console\Deployment as Deployment;

require_once 'autoloader.php';

try {
    $console = new Deployment();
} catch (\Throwable $t) {
} finally {
    exit(0);
}
