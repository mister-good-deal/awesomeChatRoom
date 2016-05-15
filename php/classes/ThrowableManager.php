<?php
/**
 * Custom exception class
 *
 * @package    Exception
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes;

use \classes\LoggerManager as Logger;
use \classes\IniManager as Ini;

/**
 * Add a logger process to the Throwable extended class
 */
class ThrowableManager
{
    /**
     * @var        Logger  $logger  A LoggerManager instance
     */
    private $logger;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that instantiate a new logger
     */
    public function __construct()
    {
        Ini::setIniFileName('conf.ini');

        $this->logger = new Logger(Ini::getParam('Exception', 'implementedLogger'));
    }

    /**
     * Log the Exception / Error thrown
     *
     * @param      \Throwable  $t      The Exception / Error thrown
     */
    public function log(\Throwable $t)
    {
        $this->logger->log($t->getCode(), $t->getMessage(), $t->getTrace());
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
