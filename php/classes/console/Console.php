<?php
/**
 * ORM console mode
 *
 * @category ORM
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\console;

use \classes\DataBase as DB;
use \classes\IniManager as Ini;
use \classes\console\ConsoleColors as ConsoleColors;

/**
 * ORM in a console mode with simple command syntax to manage the database
 *
 * @class Console
 */
class Console
{
    use \traits\PrettyOutputTrait;
    use \traits\FiltersTrait;
    use \traits\EchoTrait;

    const WELCOME = <<<'WELCOME'

 __        __   _                            _          _   _             ___  ____  __  __ 
 \ \      / /__| | ___ ___  _ __ ___   ___  | |_ ___   | |_| |__   ___   / _ \|  _ \|  \/  |
  \ \ /\ / / _ \ |/ __/ _ \| '_ ` _ \ / _ \ | __/ _ \  | __| '_ \ / _ \ | | | | |_) | |\/| |
   \ V  V /  __/ | (_| (_) | | | | | |  __/ | || (_) | | |_| | | |  __/ | |_| |  _ <| |  | |
    \_/\_/ \___|_|\___\___/|_| |_| |_|\___|  \__\___/   \__|_| |_|\___|  \___/|_| \_\_|  |_|
                                                                                            
WELCOME;
    
    const GODDBYE = <<<'GODDBYE'

   ____                 _ _                
  / ___| ___   ___   __| | |__  _   _  ___ 
 | |  _ / _ \ / _ \ / _` | '_ \| | | |/ _ \
 | |_| | (_) | (_) | (_| | |_) | |_| |  __/
  \____|\___/ \___/ \__,_|_.__/ \__, |\___|
                                |___/      
                                           
GODDBYE;

    const ACTION_CANCEL = 'Canceled';
    const ACTION_DONE   = 'Done';
    const ACTION_FAIL   = 'Failed';

    /**
     * @var string[] $COMMANDS List of all commands with their description
     */
    private static $COMMANDS = array(
        'exit'                                          => 'Exit the ORM console',
        'last cmd'                                      => 'Get the last command written',
        'all cmd'                                       => 'Get all the commands written',
        'tables'                                        => 'Get all the tables name',
        'clean -t tableName'                            => 'Delete all the row of the given table name',
        'drop -t tableName'                             => 'Drop the given table name',
        'show -t tableName [-s startIndex -e endIndex]' => 'Show table data begin at startIndex and stop at endIndex',
        'desc -t tableName'                             => 'Show table structure',
        'help'                                          => 'Display all the commands'
    );

    /**
     * @var string[] $commandsHistoric Historic of all the command written by the user in the current console session
     */
    private $commandsHistoric = array();
    /**
     * @var int $maxLength The console max characters length in a row
     */
    private $maxLength;
    /**
     * @var ConsoleColors $colors A ConsoleColors instance
     */
    private $colors;

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
        $this->colors         = new ConsoleColors();
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
            . $this->colors->getColoredString(static::WELCOME, ConsoleColors::LIGHT_RED_F, ConsoleColors::GREEN)
            . PHP_EOL
            . PHP_EOL
        );

        $this->processCommand($this->userInput());
    }

    /*-----  End of Public methods  ------*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Let the user enter a command in his console input
     *
     * @return string The command written by the user
     */
    private function userInput()
    {
        static::out($this->colors->getColoredString('> ', ConsoleColors::LIGHT_GREEN_F, ConsoleColors::BLACK));

        do {
            $handle  = fopen('php://stdin', 'r');
            $command = trim(fgets($handle));
        } while ($command === '');

        return $command;
    }

    /**
     * Process the command entered by the user and output the result in the console
     *
     * @param string $command The command passed by the user
     */
    private function processCommand($command)
    {
        $exit = false;
        preg_match('/^[a-zA-Z ]*/', $command, $commandName);

        static::out(PHP_EOL);

        switch (rtrim($commandName[0])) {
            case 'exit':
                $exit = true;
                static::out(
                    $this->colors->getColoredString(static::GODDBYE, ConsoleColors::LIGHT_RED_F, ConsoleColors::GREEN)
                    . PHP_EOL
                );
                break;

            case 'last cmd':
                static::out('The last cmd was: ' . $this->getLastCommand() . PHP_EOL);
                break;

            case 'all cmd':
                static::out('Commands historic:' . $this->tablePrettyPrint($this->commandsHistoric) . PHP_EOL);
                break;

            case 'tables':
                static::out('Tables name: ' . PHP_EOL . $this->tablePrettyPrint(DB::getAllTables()) . PHP_EOL);
                break;

            case 'clean':
                $this->cleanTable($command);
                break;

            case 'drop':
                $this->dropTable($command);
                break;

            case 'show':
                $this->showTable($command);
                break;

            case 'desc':
                $this->descTable($command);
                break;

            case 'help':
                static::out('List of all commands' . PHP_EOL . $this->tableAssociativPrettyPrint(static::$COMMANDS));
                break;

            default:
                static::out(
                    'The command : "' . $command
                    . '" is not recognized as a command, type help to display all the commands' . PHP_EOL
                );
                break;
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
     * Delete all the data in a table
     *
     * @param string $command The command passed with its arguments
     */
    private function cleanTable($command)
    {
        $args = $this->getArgs($command);

        if ($this->checkTableName($args)) {
            if ($this->confirmAction('TRUNCATE the table "' . $args['t'] .'" ? (Y/N)') === 'Y') {
                if (DB::cleanTable($args['t'])) {
                    static::out(static::ACTION_DONE . PHP_EOL);
                } else {
                    static::out(static::ACTION_FAIL . PHP_EOL . $this->tablePrettyPrint(DB::errorInfo()) . PHP_EOL);
                }
            } else {
                static::out(static::ACTION_CANCEL . PHP_EOL);
            }
        }
    }

    /**
     * Drop a table
     *
     * @param string $command The command passed with its arguments
     */
    private function dropTable($command)
    {
        $args = $this->getArgs($command);

        if ($this->checkTableName($args)) {
            if ($this->confirmAction('DROP the table "' . $args['t'] .'" ? (Y/N)') === 'Y') {
                if (DB::dropTable($args['t'])) {
                    static::out(static::ACTION_DONE . PHP_EOL);
                } else {
                    static::out(static::ACTION_FAIL . PHP_EOL . $this->tablePrettyPrint(DB::errorInfo()) . PHP_EOL);
                }
            } else {
                static::out(static::ACTION_CANCEL . PHP_EOL);
            }
        }
    }

    /**
     * Display the data of a table
     *
     * @param string $command The commande passed by the user with its arguments
     */
    private function showTable($command)
    {
        $args = $this->getArgs($command);
        $data = null;

        if ($this->checkTableName($args)) {
            if (isset($args['s']) && isset($args['e']) && is_numeric($args['s']) && is_numeric($args['e'])) {
                $data = DB::showTable($args['t'], $args['s'], $args['e']);
            } else {
                $data = DB::showTable($args['t']);
            }
        }

        if ($data !== null) {
            static::out($this->prettySqlResult($args['t'], $data) . PHP_EOL);
        }
    }

    /**
     * Display the description of a table
     *
     * @param string $command The commande passed by the user with its arguments
     */
    private function descTable($command)
    {
        $args = $this->getArgs($command);

        if ($this->checkTableName($args)) {
            static::out($this->prettySqlResult($args['t'], DB::descTable($args['t'])) . PHP_EOL);
        }
    }

    /**
     * Get the last command passed by the user
     *
     * @return string The last command
     */
    private function getLastCommand()
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
     * Check if the table is set and if the table exists
     *
     * @param  string[] $args The command arguments
     * @return boolean        True if the table exists else false
     */
    private function checkTableName($args)
    {
        $check = true;

        if (!isset($args['t'])) {
            static::out('You need to specify a table name with -t parameter' . PHP_EOL);
            $check = false;
        } elseif (!in_array($args['t'], DB::getAllTables())) {
            static::out('The table "' . $args['t'] . '" does not exist' . PHP_EOL);
            $check = false;
        }

        return $check;
    }

    /**
     * Ask the user to confirm the action
     *
     * @param  string $message The message to prompt
     * @return string          The user response casted in lower case
     */
    private function confirmAction($message)
    {
        static::out($message . PHP_EOL);

        return strtoupper($this->userInput());
    }

    /**
     * Get the command arguments in an array (argName => argValue)
     *
     * @param  string $command The command
     * @return array           The arguments in an array (argName => argValue)
     */
    private function getArgs($command)
    {
        preg_match_all('/\-(?P<argKey>[a-zA-Z]+) (?P<argValue>[a-zA-Z0-9 _]+)/', $command, $matches);

        return $this->filterPregMatchAllWithFlags($matches, 'argKey', 'argValue');
    }

    /**
     * Pretty output a table without keys
     *
     * @param  array $table The table to print
     * @return string       The pretty output table data
     */
    private function tablePrettyPrint($table)
    {
        return PHP_EOL . '- ' . implode(PHP_EOL . '- ', $table);
    }

    /**
     * Pretty output a table with keys
     *
     * @param  array  $table The associative array to print
     * @return string        The pretty output table data
     */
    private function tableAssociativPrettyPrint($table)
    {
        $keys =  array_keys($table);

        $string = '';

        foreach ($table as $key => $value) {
            $string .= $this->smartAlign($key, $keys) . ' : ' . $value . PHP_EOL;
        }

        return PHP_EOL . $string;
    }

    /**
     * Format the SQL result in a pretty output
     *
     * @param  string $tableName The table name
     * @param  array  $data      Array containing the SQL result
     * @return string            The pretty output
     */
    private function prettySqlResult($tableName, $data)
    {
        $columns       = $this->filterFecthAllByColumn($data);
        $colmunsNumber = count($columns);
        $rowsNumber    = ($colmunsNumber > 0) ? count($columns[key($columns)]) : 0;
        $columnsName   = array();
        $maxLength     = 0;

        foreach ($columns as $columnName => $column) {
            $columnsName[] = $columnName;
            $this->setMaxSize($column, strlen($columnName));
            // 3 because 2 spaces and 1 | are added between name
            $maxLength += ($this->getMaxSize($column) + 3);
        }

        // don't touch it's magic ;p
        $maxLength      -= 1;

        if ($maxLength > $this->maxLength) {
            return 'The console width is to small to print the output (console max-width = ' . $this->maxLength
                . ' and content output width = ' . $maxLength . ')' . PHP_EOL;
        }

        if ($maxLength <= 0) {
            // 9 beacause strlen('No data') = 7 + 2 spaces
            $maxLength = max(strlen($tableName) + 2, 9);
        }

        $separationLine = '+' . str_pad('', $maxLength, '-', STR_PAD_BOTH) . '+' . PHP_EOL;
        $prettyString   = $separationLine;
        $prettyString   .= '|' . str_pad($tableName, $maxLength, ' ', STR_PAD_BOTH) . '|' . PHP_EOL ;
        $prettyString   .= $separationLine;

        for ($i = 0; $i < $colmunsNumber; $i++) {
            $prettyString .= '| ' . $this->smartAlign($columnsName[$i], $columns[$columnsName[$i]], 0, STR_PAD_BOTH)
                . ' ';
        }

        if ($colmunsNumber > 0) {
            $prettyString .= '|' . PHP_EOL . $separationLine;
        }


        for ($i = 0; $i < $rowsNumber; $i++) {
            for ($j = 0; $j < $colmunsNumber; $j++) {
                $prettyString .= '| ' .
                    $this->smartAlign($columns[$columnsName[$j]][$i], $columns[$columnsName[$j]]) . ' ';
            }

            $prettyString .= '|' . PHP_EOL;
        }

        if ($rowsNumber === 0) {
            $prettyString .= '|' . str_pad('No data', $maxLength, ' ', STR_PAD_BOTH) . '|' . PHP_EOL ;
        }

        return $prettyString . $separationLine;
    }

    /*-----  End of Private methods  ------*/
}
