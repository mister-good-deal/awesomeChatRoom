<?php
/**
 * Logger interface
 *
 * @package    Interface
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\logger;

use \classes\logger\LogLevel as LogLevel;
use \classes\console\ConsoleColors as ConsoleColors;
use \classes\IniManager as Ini;
use \interfaces\LoggerInterface as LoggerInterface;
use \abstracts\AbstractLogger as AbstractLogger;

/**
 * A logger which writes the log in the console
 */
class ConsoleLogger extends AbstractLogger
{
    use \traits\PrettyOutputTrait;
    use \traits\EchoTrait;

    /**
     * @var        array  $LEVELS   Logger level based on LogLevel class
     */
    public static $LEVELS = array(
        LogLevel::EMERGENCY => 'emergency',
        LogLevel::ALERT     => 'alert',
        LogLevel::CRITICAL  => 'critical',
        LogLevel::ERROR     => 'error',
        LogLevel::WARNING   => 'warning',
        LogLevel::NOTICE    => 'notice',
        LogLevel::INFO      => 'info',
        LogLevel::DEBUG     => 'debug'
    );

    /**
     * @var        ConsoleColors  $colors   ConsoleColors instance to color console output
     */
    private $colors;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that instanciates a ConsoleColors
     */
    public function __construct()
    {
        static::$echoEncoding = Ini::getParam('Console', 'encoding');
        $this->colors         = new ConsoleColors();
    }

    /*-----  End of Magic methods  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

     /**
      * System is unusable.
      *
      * @param      string  $message
      * @param      array   $context
      *
      * @return     null
      */
    public function emergency($message, array $context = array())
    {
        static::out(
            $this->colors->getColoredString(
                $message,
                ConsoleColors::WHITE_F,
                ConsoleColors::RED
            )
            . PHP_EOL
            . $this->formatContext($context)
        );
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should trigger the SMS alerts and wake you up.
     *
     * @param      string  $message
     * @param      array   $context
     *
     * @return     null
     */
    public function alert($message, array $context = array())
    {
        static::out(
            $this->colors->getColoredString(
                $message,
                ConsoleColors::LIGHT_GRAY,
                ConsoleColors::RED
            )
            . PHP_EOL
            . $this->formatContext($context)
        );
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param      string  $message
     * @param      array   $context
     *
     * @return     null
     */
    public function critical($message, array $context = array())
    {
        static::out(
            $this->colors->getColoredString(
                $message,
                ConsoleColors::RED,
                ConsoleColors::LIGHT_GRAY
            )
            . PHP_EOL
            . $this->formatContext($context)
        );
    }

    /**
     * Runtime errors that do not require immediate action but should typically be logged and monitored.
     *
     * @param      string  $message
     * @param      array   $context
     *
     * @return     null
     */
    public function error($message, array $context = array())
    {
        static::out(
            $this->colors->getColoredString(
                $message,
                ConsoleColors::LIGHT_RED_F,
                ConsoleColors::LIGHT_GRAY
            )
            . PHP_EOL
            . $this->formatContext($context)
        );
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things that are not necessarily wrong.
     *
     * @param      string  $message
     * @param      array   $context
     *
     * @return     null
     */
    public function warning($message, array $context = array())
    {
        static::out(
            $this->colors->getColoredString(
                $message,
                ConsoleColors::YELLOW,
                ConsoleColors::BLACK
            )
            . PHP_EOL
            . $this->formatContext($context)
        );
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param      string  $message
     * @param      array   $context
     *
     * @return     null
     */
    public function notice($message, array $context = array())
    {
        static::out(
            $this->colors->getColoredString(
                $message,
                ConsoleColors::LIGHT_GRAY,
                ConsoleColors::BLACK
            )
            . PHP_EOL
            . $this->formatContext($context)
        );
    }

    /**
     * Detailed debug information.
     *
     * @param      string  $message
     * @param      array   $context
     *
     * @return     null
     */
    public function info($message, array $context = array())
    {
        static::out(
            $this->colors->getColoredString(
                $message,
                ConsoleColors::LIGHT_GREEN_F,
                ConsoleColors::BLACK
            )
            . PHP_EOL
            . $this->formatContext($context)
        );
    }

    /**
     * Informations détaillées de débogage.
     *
     * @param      string  $message
     * @param      array   $context
     *
     * @return     null
     */
    public function debug($message, array $context = array())
    {
        static::out(
            $this->colors->getColoredString(
                $message,
                ConsoleColors::CYAN,
                ConsoleColors::BLACK
            )
            . PHP_EOL
            . $this->formatContext($context)
        );
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param      mixed   $level
     * @param      string  $message
     * @param      array   $context
     *
     * @return     null
     */
    public function log($level, $message, array $context = array())
    {
        if (in_array($level, array_keys(static::$LEVELS))) {
            call_user_func(__CLASS__ . '::' . static::$LEVELS[$level], $message, $context);
        } else {
            $this->info($message, $context);
        }
    }

    /*-----  End of Public methods  ------*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Helper method to pretty output info with colors defined for each type of context
     *
     * @param      array   $contexts  The context
     *
     * @return     string  The output result as a string
     */
    private function formatContext($contexts)
    {
        $string = '';

        foreach ($contexts as $num => $context) {
            if (is_array($context)) {
                $string .= PHP_EOL . $this->colors->getColoredString(
                    'Context: ' . ($num + 1),
                    ConsoleColors::YELLOW,
                    ConsoleColors::BLACK
                ) . PHP_EOL;

                $string .= $this->formatContextShortcut('in #file:', $context, 'file');
                $string .= $this->formatContextShortcut('in #class:', $context, 'class');
                $string .= $this->formatContextShortcut('in #function:', $context, 'function');
                $string .= $this->formatContextShortcut('at #line:', $context, 'line');
                $string .= $this->formatContextShortcut('with arguments:', $context, 'args');
            }
        }

        return $string;
    }

    /**
     * Return arguments in a formatted string with type and value
     *
     * @param      array   $arguments  The arguments
     *
     * @return     string  The arguments in a formatted string
     */
    private function formatArguments($arguments)
    {
        $argumentsFormatted = array();

        foreach ($arguments as $argument) {
            $argumentsFormatted[] = $this->formatVariable($argument, 2);
        }

        return '(' . implode(', ', $argumentsFormatted) . ')';
    }

    /**
     * Utility method to format a context string
     *
     * @param      string    $description  Context description
     * @param      string[]  $context      Context array
     * @param      string    $type         Context type
     * @param      string    $fgColor1     Description foreground color DEFAULT ConsoleColors::PURPLE_F
     * @param      string    $bgColor1     Description background color DEFAULT ConsoleColors::BLACK
     * @param      string    $fgColor2     Context foreground color DEFAULT ConsoleColors::YELLOW
     * @param      string    $bgColor2     Context background color DEFAULT ConsoleColors::BLACK
     *
     * @return     string    The formatted context string
     */
    private function formatContextShortcut(
        $description,
        $context,
        $type,
        $fgColor1 = ConsoleColors::PURPLE_F,
        $bgColor1 = ConsoleColors::BLACK,
        $fgColor2 = ConsoleColors::YELLOW,
        $bgColor2 = ConsoleColors::BLACK
    ) {
        $formatedString = '';

        if (isset($context[$type])) {
            if ($type === 'args') {
                $contextString = $this->formatArguments($context[$type]);
            } else {
                $contextString = $context[$type];
            }

            $formatedString = "\t"
                . $this->colors->getColoredString($description, $fgColor1, $bgColor1)
                . "\t"
                . $this->colors->getColoredString($contextString, $fgColor2, $bgColor2)
                . PHP_EOL;
        }

        return $formatedString;
    }

    /*-----  End of Private methods  ------*/
}
