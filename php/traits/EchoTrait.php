<?php
/**
 * Trait to use echo with pre-encoding
 *
 * @category Trait
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace traits;

use \classes\IniManager as Ini;
use \classes\console\ConsoleColors as ConsoleColors;
use \vendors\ChromePhp as Console;

/**
 * Utility methods to use echo with pre-encoding for console or format for html
 *
 * @trait EchoTrait
 */
trait EchoTrait
{
    /**
     * @var string $echoEncoding The enconding to encode every console output DEFAULT UTF-8
     */
    public static $echoEncoding = 'UTF-8';

    /**
     * Echo shortcut but with a encoding conversion before output
     *
     * @param  string $output The string to output in the console
     * @static
     */
    public static function out($output)
    {
        $environment = Ini::getParam('Environment', 'environment');

        switch ($environment) {
            case 'console':
                echo mb_convert_encoding($output, static::$echoEncoding);

                break;

            case 'web':
                foreach (preg_split('/' . PHP_EOL . '/', ConsoleColors::unsetColor($output)) as $line) {
                    Console::log($line);
                }

                break;
            
            default:
                echo $output;

                break;
        }
    }

    /**
     * Convert a console output to a HTML output
     *
     * @param  string $output The console formatted output
     * @return string         The HTML formated output
     * @static
     */
    private static function convertConsoleToHtml($output)
    {
        return preg_replace('/' . PHP_EOL . '/', '<br>', $output);
    }
}
