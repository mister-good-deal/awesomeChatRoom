<?php
/**
 * Trait to use echo with pre-encoding
 *
 * @category Trait
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace traits;

/**
 * Utility methods to use echo with pre-encoding
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
        echo mb_convert_encoding($output, static::$echoEncoding);
    }
}
