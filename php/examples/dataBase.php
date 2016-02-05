<?php
/**
 * Example of DataBase class used
 *
 * @package    Example
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */
use \classes\DataBase as DB;

require_once '\utilities\autoloader.php';

try {
    DB::beginTransaction();

    if (DB::exec('DELETE FROM table WHERE 1 = 1') > 1) {
        DB::rollBack();
    } else {
        DB::commit();
    }
} catch (\Throwable $t) {
    echo $e->getMessage() . PHP_EOL;
} finally {
    exit(0);
}
