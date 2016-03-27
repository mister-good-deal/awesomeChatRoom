<?php
/**
 * Launch a deployment instance
 *
 * @package    Launcher
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

declare(strict_types=1);

use \classes\console\Deployment as Deployment;
use \classes\ExceptionManager as Exception;

require_once 'autoloader.php';

try {
    $console = new Deployment();
} catch (\Throwable $t) {
    echo $t . PHP_EOL;
    $throwable = new Exception($t->getMessage(), $t->getCode(), $t->getPrevious());
    exit(-1);
}

exit(0);
