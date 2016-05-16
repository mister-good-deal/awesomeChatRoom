<?php
/**
 * Abstracts logger class
 *
 * @package    Abstract
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace abstracts;

use \classes\logger\LogLevel as LogLevel;
use \interfaces\LoggerInterface as LoggerInterface;

/**
 * This is a simple Logger implementation that other Loggers can inherit from.
 *
 * It simply delegates all log-level-specific methods to the `log` method to reduce boilerplate code that a simple
 * Logger that does the same thing with messages regardless of the error level has to implement.
 *
 * @class    AbstractLogger @abstract
 */
abstract class AbstractLogger implements LoggerInterface
{
    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * System is unusable.
     *
     * @param      string  $message
     * @param      array   $context
     */
    public function emergency(string $message, array $context = array())
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should trigger the SMS alerts and wake you up.
     *
     * @param      string  $message
     * @param      array   $context
     */
    public function alert(string $message, array $context = array())
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param      string  $message
     * @param      array   $context
     */
    public function critical(string $message, array $context = array())
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically be logged and monitored.
     *
     * @param      string  $message
     * @param      array   $context
     */
    public function error(string $message, array $context = array())
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things that are not necessarily wrong.
     *
     * @param      string  $message
     * @param      array   $context
     */
    public function warning(string $message, array $context = array())
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param      string  $message
     * @param      array   $context
     */
    public function notice(string $message, array $context = array())
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param      string  $message
     * @param      array   $context
     */
    public function info(string $message, array $context = array())
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param      string  $message
     * @param      array   $context
     */
    public function debug(string $message, array $context = array())
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /*-----  End of Public methods  ------*/
}
