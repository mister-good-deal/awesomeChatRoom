<?php
/**
 * ORM console mode
 *
 * @package    Console
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\console;

use \classes\IniManager as Ini;
use \classes\console\ConsoleColors as ConsoleColors;

/**
 * Console mode basic interface
 */
class Console
{
    use \traits\PrettyOutputTrait;
    use \traits\FiltersTrait;
    use \traits\EchoTrait;

    const WELCOME = <<<'WELCOME'
                                            `
 __        __   _                           `
 \ \      / /__| | ___ ___  _ __ ___   ___  `
  \ \ /\ / / _ \ |/ __/ _ \| '_ ` _ \ / _ \ `
   \ V  V /  __/ | (_| (_) | | | | | |  __/ `
    \_/\_/ \___|_|\___\___/|_| |_| |_|\___| `
                                            `
WELCOME;

    const GODDBYE = <<<'GODDBYE'

   ____                 _ _                 `
  / ___| ___   ___   __| | |__  _   _  ___  `
 | |  _ / _ \ / _ \ / _` | '_ \| | | |/ _ \ `
 | |_| | (_) | (_) | (_| | |_) | |_| |  __/ `
  \____|\___/ \___/ \__,_|_.__/ \__, |\___| `
                                |___/       `
                                            `
GODDBYE;

    const ACTION_CANCEL = 'Canceled';
    const ACTION_DONE   = 'Done';
    const ACTION_FAIL   = 'Failed';

    /**
     * @var        string[]  $COMMANDS  List of all commands with their description
     */
    protected static $COMMANDS = array(
        'exit'                                               => 'Exit the ORM console',
        'last cmd'                                           => 'Get the last command written',
        'all cmd'                                            => 'Get all the commands written',
        'help'                                               => 'Display all the commands'
    );

    /**
     * @var        string[]  $commandsHistoric  Historic of all the command written by the user in the current console session
     */
    protected $commandsHistoric = array();
    /**
     * @var        int   $maxLength     The console max characters length in a row
     */
    protected $maxLength;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor
     */
    public function __construct()
    {
        static::$echoEncoding = Ini::getParam('Console', 'encoding');
        $this->maxLength      = Ini::getParam('Console', 'maxLength');
    }

    /*-----  End of Magic methods  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Launch a console session
     */
    public function launchConsole()
    {
        static::out(
            PHP_EOL
            . ConsoleColors::getColoredString(static::WELCOME, ConsoleColors::LIGHT_RED_F, ConsoleColors::GREEN)
            . PHP_EOL
            . PHP_EOL
        );

        $this->processCommand($this->userInput());
    }

    /*-----  End of Public methods  ------*/

    /*=======================================
    =            Protected methods            =
    =======================================*/

    /**
     * Let the user enter a command in his console input
     *
     * @return     string  The command written by the user
     */
    protected function userInput(): string
    {
        static::out(ConsoleColors::getColoredString('> ', ConsoleColors::LIGHT_GREEN_F, ConsoleColors::BLACK));

        do {
            $handle  = fopen('php://stdin', 'r');
            $command = trim(fgets($handle));
        } while ($command === '');

        return $command;
    }

    /**
     * Process the command entered by the user and output the result in the console
     *
     * @param      string  $command   The command passed by the user
     * @param      bool    $executed  DEFAULT false, true if the command is already executed, else false
     */
    protected function processCommand(string $command, bool $executed = false)
    {
        $exit = false;
        preg_match('/^[a-zA-Z ]*/', $command, $commandName);

        if (!$executed) {
            switch (rtrim($commandName[0])) {
                case 'exit':
                    $exit = true;
                    static::out(
                        ConsoleColors::getColoredString(
                            static::GODDBYE,
                            ConsoleColors::LIGHT_RED_F,
                            ConsoleColors::GREEN
                        )
                        . PHP_EOL
                    );
                    break;

                case 'last cmd':
                    static::out('The last cmd was: ' . $this->getLastCommand() . PHP_EOL);
                    break;

                case 'all cmd':
                    static::out('Commands historic:' . $this->tablePrettyPrint($this->commandsHistoric) . PHP_EOL);
                    break;

                case 'help':
                    static::out('List of all command' . PHP_EOL . $this->tableAssociativPrettyPrint(static::$COMMANDS));
                    break;

                default:
                    static::out(
                        'The command : "' . $command
                        . '" is not recognized as a command, type help to display all the commands' . PHP_EOL
                    );
                    break;
            }
        }

        static::out(PHP_EOL);

        if ($command !== $this->getLastCommand()) {
            $this->commandsHistoric[] = $command;
        }

        if (!$exit) {
            $this->processCommand($this->userInput());
        }
    }

    /**
     * Get the last command passed by the user
     *
     * @return     string  The last command
     */
    protected function getLastCommand(): string
    {
        $nbCommands = count($this->commandsHistoric);

        if ($nbCommands > 0) {
            $cmd = $this->commandsHistoric[$nbCommands - 1];
        } else {
            $cmd = '';
        }

        return $cmd;
    }

    /**
     * Ask the user to confirm the action
     *
     * @param      string  $message  The message to prompt
     *
     * @return     string  The user response casted in lower case
     */
    protected function confirmAction(string $message): string
    {
        static::out($message . PHP_EOL);

        return strtoupper($this->userInput());
    }

    /**
     * Get the command arguments in an array (argName => argValue)
     *
     * @param      string  $command  The command
     *
     * @return     array   The arguments in an array (argName => argValue)
     */
    protected function getArgs(string $command): array
    {
        // -argument = value
        preg_match_all('/\-(?P<argKey>[a-zA-Z]+) (?P<argValue>("[^"]+"|[a-zA-Z0-9 _]+))/', $command, $matches1);

        if (isset($matches1['argValue'][1])) {
            $matches1['argValue'][1] = str_replace('"', '', $matches1['argValue'][1]);
        }

        // --argument
        preg_match_all('/\-\-(?P<argKey>[a-zA-Z\-]+)(?P<argValue>[^ \-\-]*)/', $command, $matches2);

        $args1 = $this->filterPregMatchAllWithFlags($matches1, 'argKey', 'argValue');
        $args2 = $this->filterPregMatchAllWithFlags($matches2, 'argKey', 'argValue');

        return $args1 + $args2;
    }

    /**
     * Pretty output a table without keys
     *
     * @param      array   $table  The table to print
     *
     * @return     string  The pretty output table data
     */
    protected function tablePrettyPrint(array $table): string
    {
        return PHP_EOL . '- ' . implode(PHP_EOL . '- ', $table);
    }

    /**
     * Pretty output a table with keys
     *
     * @param      array   $table  The associative array to print
     *
     * @return     string  The pretty output table data
     */
    protected function tableAssociativPrettyPrint(array $table): string
    {
        $keys =  array_keys($table);

        $string = '';

        foreach ($table as $key => $value) {
            $string .= $this->smartAlign($key, $keys) . ' : ' . $value . PHP_EOL;
        }

        return PHP_EOL . $string;
    }

    /*-----  End of Protected methods  ------*/
}
