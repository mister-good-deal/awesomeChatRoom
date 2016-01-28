<?php
/**
 * ORM console mode
 *
 * @category ORM
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\console;

use \classes\console\Console as Console;
use \classes\DataBase as DB;
use \classes\IniManager as Ini;
use \classes\entitiesManager\GlobalEntityManager as EntityManager;
use \abstracts\designPatterns\Entity as Entity;

/**
 * ORM in a console mode with simple command syntax to manage the database
 */
class Orm extends Console
{
    use \traits\PrettyOutputTrait;
    use \traits\FiltersTrait;
    use \traits\EchoTrait;

    /**
     * @var string[] $SELF_COMMANDS List of all commands with their description
     */
    private static $SELF_COMMANDS = array(
        'tables'                                             => 'Get all the tables name',
        'entities'                                           => 'Get all the entites name',
        'entity -n entityName --clean|drop|show|desc|create' => 'Perform action on entity table',
        'clean -t tableName'                                 => 'Delete all the row of the given table name',
        'drop -t tableName'                                  => 'Drop the given table name',
        'show -t tableName [-s startIndex -e endIndex]'      => 'Show table data begin at startIndex and stop at endIndex',
        'desc -t tableName'                                  => 'Show table structure',
        'create all'                                         => 'Create all the tables'
    );

    /**
     * Call the parent constructor and merge the commands list
     */
    public function __construct()
    {
        parent::__construct();
        parent::$COMMANDS = array_merge(parent::$COMMANDS, static::$SELF_COMMANDS);
    }

    /**
     * @inheritDoc
     */
    protected function processCommand($command, $executed = false)
    {
        $executed = true;

        preg_match('/^[a-zA-Z ]*/', $command, $commandName);

        static::out(PHP_EOL);

        switch (rtrim($commandName[0])) {
            case 'tables':
                static::out('Tables name: ' . PHP_EOL . $this->tablePrettyPrint(DB::getAllTables()) . PHP_EOL);
                break;

            case 'entities':
                static::out('Tables name: ' . PHP_EOL . $this->tablePrettyPrint(DB::getAllEntites()) . PHP_EOL);
                break;

            case 'entity':
                $this->entityProcess($command);
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

            case 'create all':
                $this->createAllTables();
                break;

            default:
                $executed = false;
                break;
        }

        parent::processCommand($command, $executed);
    }

    /**
     * Process the command called on the entity
     *
     * @param string $command The command passed with its arguments
     */
    private function entityProcess($command)
    {
        $args = $this->getArgs($command);

        if ($this->checkEntityName($args)) {
            /**
             * @var Entity $entity An entity
             */
            $entityClassPath = Ini::getParam('Entities', 'entitiesClassPath') . DIRECTORY_SEPARATOR . $args['n'];
            $entity         = new $entityClassPath;
            $command       .= ' -t ' . strtolower($entity->getTableName()); // todo bug SQL table name with uppercase

            if (isset($args['clean'])) {
                $this->cleanTable($command);
            } elseif (isset($args['drop'])) {
                $this->dropTable($command);
            } elseif (isset($args['show'])) {
                $this->showTable($command);
            } elseif (isset($args['desc'])) {
                $this->descTable($command);
            } elseif (isset($args['create'])) {
                $entityManager = new EntityManager();
                $entityManager->setEntity($entity);
                $entityManager->createEntityTable();
                static::out(static::ACTION_DONE . PHP_EOL);
            }
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
     * Create all the entities table
     */
    private function createAllTables()
    {
        $entityManager = new EntityManager();

        foreach (DB::getAllEntites() as $entityName) {
            /**
             * @var Entity $entity An entity
             */
            $entityClassPath = Ini::getParam('Entities', 'entitiesClassPath') . DIRECTORY_SEPARATOR . $entityName;
            $entity          = new $entityClassPath;
            $tableName       = strtolower($entity->getTableName()); // todo bug SQL table name with uppercase

            if (!in_array($tableName, DB::getAllTables())) {
                static::out('Create table "' . $tableName . '"' . PHP_EOL);
                $entityManager->setEntity($entity);
                $entityManager->createEntityTable();
            } else {
                static::out('Table table "' . $tableName . '" already exists' . PHP_EOL);
            }
        }

        static::out(static::ACTION_DONE . PHP_EOL);
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
     * Check if the entity exists
     *
     * @param  string[] $args The command arguments
     * @return boolean        True if the entity exists else false
     */
    private function checkEntityName($args)
    {
        $check = true;

        if (!isset($args['n'])) {
            static::out('You need to specify an entity name with -n parameter' . PHP_EOL);
            $check = false;
        } elseif (!in_array($args['n'], DB::getAllEntites())) {
            static::out('The entity "' . $args['n'] . '" does not exist' . PHP_EOL);
            $check = false;
        }

        return $check;
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
}
