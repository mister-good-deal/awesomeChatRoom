<?php
/**
 * File logger
 *
 * @package    Logger
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\logger;

use classes\IniManager as Ini;
use abstracts\AbstractLogger as AbstractLogger;

/**
 * File logger to log  exceptions in a file
 */
class FileLogger extends AbstractLogger
{
    /**
     * @var        string  $filePath    The file path to write the log in (can be the file name if the rep is in the include path)
     */
    private $filePath;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that take the file path as a first parameter, if omitted it loads the file path defined in the ini
     *
     * @param      string  $filePath  OPTIONAL the file path
     */
    public function __construct(string $filePath = null)
    {
        if ($filePath !== null && is_string($filePath)) {
            $this->filePath = $filePath;
        } else {
            $this->filePath = Ini::getParam('FileLogger', 'filePath');
        }
    }

    /*-----  End of Magic methods  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Logs with an arbitrary level.
     *
     * @param      mixed   $level
     * @param      string  $message
     * @param      array   $context
     */
    public function log($level, string $message, array $context = array())
    {
        $this->writeInFile($message);
    }

    /*-----  End of Public methods  ------*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Utility methods to format and write the log in a file
     *
     * @param      string  $message  The error message to write
     *
     * @todo       Use the context
     */
    private function writeInFile(string $message)
    {
        $string = date('Y-m-d H:i:s')
            . "\t\t"
            . $message
            . PHP_EOL;

        file_put_contents($this->filePath, $string, FILE_APPEND);
    }

    /*-----  End of Private methods  ------*/
}
