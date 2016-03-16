<?php
/**
 * Launch an ORM instance
 *
 * @package    Launcher
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

use \classes\console\Orm as Orm;
use \classes\ExceptionManager as Exception;

require_once 'autoloader.php';

try {
    $console = new Orm();
} catch (\Throwable $t) {
    $throwable = new Exception($t->getMessage(), $t->getCode(), $t);
} finally {
    exit(0);
}
