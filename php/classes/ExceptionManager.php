<?php
/**
 * Custom exception class
 *
 * @package    Exception
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes;

use classes\LoggerManager as Logger;
use classes\logger\LogLevel as LogLevel;
use classes\IniManager as Ini;

/**
 * Add a logger process to the class exception throw
 */
class ExceptionManager extends \Exception
{
    /**
     * @var        int   $EMERGENCY     EMERGENCY log level
     */
    public static $EMERGENCY =  LogLevel::EMERGENCY;
    /**
     * @var        int   $ALERT     ALERT log level
     */
    public static $ALERT     =  LogLevel::ALERT;
    /**
     * @var        int   $CRITICAL  CRITICAL log level
     */
    public static $CRITICAL  =  LogLevel::CRITICAL;
    /**
     * @var        int   $ERROR     ERROR log level
     */
    public static $ERROR     =  LogLevel::ERROR;
    /**
     * @var        int   $WARNING   WARNING log level
     */
    public static $WARNING   =  LogLevel::WARNING;
    /**
     * @var        int   $NOTICE    NOTICE log level
     */
    public static $NOTICE    =  LogLevel::NOTICE;
    /**
     * @var        int   $INFO  INFO log level
     */
    public static $INFO      =  LogLevel::INFO;
    /**
     * @var        int   $DEBUG     DEBUG log level
     */
    public static $DEBUG     =  LogLevel::DEBUG;
    /**
     * @var        int   $PARAMETER     PARAMETER log level
     */
    public static $PARAMETER =  LogLevel::PARAMETER;

    /**
     * @var        Logger  $logger  A LoggerManager instance
     */
    private $logger;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that called the parent \Exception constructor
     *
     * @param      string                $message  Error message
     * @param      int                   $code     Error level
     * @param      \Exception|null       $previous Previous \Exception or \Error
     */
    public function __construct(string $message, int $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
        Ini::setIniFileName('conf.ini');

        $this->logger = new Logger(Ini::getParam('Exception', 'implementedLogger'));
        $this->logger->log($code, $message, parent::getTrace());
    }

    /*-----  End of Magic methods  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Add a logger to the implemented logger
     *
     * @param      int   $loggerType  The logger type
     */
    public function addLogger(int $loggerType)
    {
        $this->logger->addLogger($loggerType);
    }

    /**
     * Remove a logger to the implemented logger
     *
     * @param      int   $loggerType  The logger type
     */
    public function removeLogger(int $loggerType)
    {
        $this->logger->removeLogger($loggerType);
    }

    /*-----  End of Public methods  ------*/
}
