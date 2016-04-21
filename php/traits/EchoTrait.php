<?php
/**
 * Trait to use echo with pre-encoding
 *
 * @package    Trait
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace traits;

use \classes\IniManager as Ini;
use \classes\console\ConsoleColors as ConsoleColors;
use \vendors\ChromePhp as Console;

/**
 * Utility methods to use echo with pre-encoding for console or format for html
 */
trait EchoTrait
{
    /**
     * @var        string  $echoEncoding    The enconding to encode every console output DEFAULT UTF-8
     */
    public static $echoEncoding = 'UTF-8';

    /**
     * Echo shortcut but with a encoding conversion before output
     *
     * @param      string  $output  The string to output in the console
     * @param      string  $prefix  A prefix to add to teh output DEFAULT ''
     *
     * @static
     */
    public static function out(string $output, string $prefix = '')
    {
        Ini::setIniFileName(Ini::INI_CONF_FILE);
        $environment = Ini::getParam('Environment', 'environment');

        if (!isset($_SERVER['REQUEST_URI'])) {
            $environment = 'console';
        }

        switch ($environment) {
            case 'console':
                echo mb_convert_encoding($prefix . $output, static::$echoEncoding);

                break;

            case 'web':
                foreach (preg_split('/' . PHP_EOL . '/', ConsoleColors::unsetColor($output)) as $line) {
                    Console::log($line);
                }

                break;

            default:
                echo $prefix . $output;

                break;
        }
    }

    /**
     * Shortcut to output with OK prefix
     *
     * @param      string  $output  The string to output in the console
     */
    public static function ok(string $output)
    {
        static::out($output, ConsoleColors::OK());
    }

    /**
     * Shortcut to output with FAIL prefix
     *
     * @param      string  $output  The string to output in the console
     */
    public static function fail(string $output)
    {
        static::out($output, ConsoleColors::FAIL());
    }

    /**
     * Execute a command and display the result in the console
     *
     * @param      string  $cmd    The command to run
     */
    public static function execWithPrint(string $cmd)
    {
        $result = array();

        exec($cmd, $result);

        foreach ($result as $line) {
            static::out($line . PHP_EOL);
        }
    }

    /**
     * Execute a command and display the result in the console in live
     *
     * @param      string  $cmd    The command to run
     */
    public static function execWithPrintInLive(string $cmd)
    {
        $proc = popen($cmd, 'r');

        while (!feof($proc)) {
            static::out(fread($proc, 4096));
        }
    }

    /**
     * Convert a console output to a HTML output
     *
     * @param      string  $output  The console formatted output
     * @return     string  The HTML formated output
     *
     * @static
     */
    private static function convertConsoleToHtml(string $output): string
    {
        return preg_replace('/' . PHP_EOL . '/', '<br>', $output);
    }
}
