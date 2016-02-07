<?php
/**
 * Test the Database class
 *
 * @package    Test
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace tests;

use \classes\DataBase as DB;
use \classes\AssertionErrorManager as AssertionErrorManager;
use \classes\logger\LogLevel as LogLevel;
use \classes\console\ConsoleColors as Color;

require_once '../autoloader.php';

const DSN      = 'mysql:dbname=websocket;host=127.0.0.1';
const USERNAME = 'root';
const PASSWORD = 'root';

ini_set('zend.assertions', 1);
ini_set('assert.exception', 1);

class Traits
{
    use \traits\EchoTrait;
}

$color = new Color();
$ok    = '[' . $color->getColoredString('OK', Color::GREEN) . "]\t";
$fail  = '[' . $color->getColoredString('FAIL', Color::RED) . "]\t";

$tests = array(
    'Getters / setters' => array(
        'dsn' => function ($ok) {
            DB::setDsn(DSN);
            assert(DB::getDsn() === DSN, new \AssertionError('Get / set dsn is broken', LogLevel::EMERGENCY));
            Traits::out($ok . 'Get / set dsn' . PHP_EOL);
        },
        'username' => function ($ok) {
            DB::setUsername(USERNAME);
            assert(DB::getUsername() !== USERNAME, new \AssertionError('Get / set username is broken', LogLevel::EMERGENCY));
            Traits::out($ok . 'Get / set username' . PHP_EOL);
        },
        'password' => function ($ok) {
            DB::setPassword(PASSWORD);
            assert(DB::getPassword() === PASSWORD, new \AssertionError('Get / set password is broken', LogLevel::EMERGENCY));
            Traits::out($ok . 'Get / set password' . PHP_EOL);
        }
    )
);

foreach ($tests as $section => $sectionTests) {
    foreach ($sectionTests as $section => $test) {
        try {
            $test($ok);
        } catch (\Throwable $t) {
            Traits::out($fail);
            new AssertionErrorManager($t->getMessage(), $t->getCode(), $t->getPrevious());
        }
    }
}
