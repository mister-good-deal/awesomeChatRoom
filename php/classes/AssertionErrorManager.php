<?php
/**
 * Custom assertion error class
 *
 * @package    Exception
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes;

use \classes\LoggerManager as Logger;
use \classes\IniManager as Ini;

/**
 * Class to customize assertion error output
 */
class AssertionErrorManager extends \AssertionError
{
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
     * @param      string                $message   Error message
     * @param      int                   $code      Error level
     * @param      \AssertionError|null  $previous  Previous \Exception
     */
    public function __construct(string $message, int $code = 0, \AssertionError $previous = null)
    {
        Ini::setIniFileName('conf.ini');

        $this->logger = new Logger(Ini::getParam('Assertion', 'implementedLogger'));
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
