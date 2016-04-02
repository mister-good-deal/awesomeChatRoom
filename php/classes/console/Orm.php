<?php
/**
 * ORM console mode
 *
 * @package    ORM
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\console;

use \classes\console\Console as Console;
use \classes\DataBase as DB;
use \classes\IniManager as Ini;
use \classes\entitiesManager\GlobalEntityManager as EntityManager;
use \classes\managers\UserManager as UserManager;
use \classes\managers\ChatManager as ChatManager;
use \classes\entities\User as User;
use \classes\entities\UserRight as UserRight;
use \classes\entities\ChatRoom as ChatRoom;
use \classes\entitiesCollection\UserCollection as UserCollection;
use \classes\entitiesCollection\ChatRoomCollection as ChatRoomCollection;
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
     * @var        string[]  $SELF_COMMANDS     List of all commands with their description
     */
    private static $SELF_COMMANDS = array(
        'tables'                                             => 'Get all the tables name',
        'entities'                                           => 'Get all the entites name',
        'entity -n entityName --clean|drop|show|desc|create' => 'Perform action on entity table',
        'clean -t tableName'                                 => 'Delete all the row of the given table name',
        'drop -t tableName'                                  => 'Drop the given table name',
        'show -t tableName [-s startIndex -e endIndex]'      => 'Show table data begin at startIndex and stop at endIndex',
        'desc -t tableName'                                  => 'Show table structure',
        'create all'                                         => 'Create all tables',
        'generate data'                                      => 'Generate default data in all tables',
        'init'                                               => '`create all` + `generate data`',
        'es --create-index'                                  => 'Create an elasticsearch index'
    );

    /**
     * Call the parent constructor, merge the commands list and launch the console
     */
    public function __construct()
    {
        parent::__construct();
        parent::$COMMANDS = array_merge(parent::$COMMANDS, static::$SELF_COMMANDS);
        $this->launchConsole();
    }

    /**
     * @inheritDoc
     */
    protected function processCommand(string $command, bool $executed = false)
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

            case 'generate data':
                $this->insertUserData();
                $this->insertChatData();
                break;

            case 'init':
                $this->init();
                break;

            case 'es':
                $this->elasticsearchProcess($command);
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
     * @param      string  $command  The command passed with its arguments
     */
    private function entityProcess(string $command)
    {
        $args = $this->getArgs($command);

        if ($this->checkEntityName($args)) {
            /**
             * @var        Entity  $entity  An entity
             */
            $entityClassNamespace = Ini::getParam('Entities', 'entitiesClassNamespace') . '\\' . $args['n'];
            $entity         = new $entityClassNamespace;
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
                static::ok(static::ACTION_DONE . PHP_EOL);
            }
        }
    }

    private function elasticsearchProcess(string $command)
    {
        $args = $this->getArgs($command);

        if (isset($args['create-index'])) {
            $this->createElasticsearchIndex();
        }
    }

    /**
     * Delete all the data in a table
     *
     * @param      string  $command  The command passed with its arguments
     */
    private function cleanTable(string $command)
    {
        $args = $this->getArgs($command);

        if ($this->checkTableName($args)) {
            if ($this->confirmAction('TRUNCATE the table "' . $args['t'] .'" ? (Y/N)') === 'Y') {
                if (DB::cleanTable($args['t'])) {
                    static::ok(static::ACTION_DONE . PHP_EOL);
                } else {
                    static::fail(static::ACTION_FAIL . PHP_EOL . $this->tablePrettyPrint(DB::errorInfo()) . PHP_EOL);
                }
            } else {
                static::out(static::ACTION_CANCEL . PHP_EOL);
            }
        }
    }

    /**
     * Drop a table
     *
     * @param      string  $command  The command passed with its arguments
     */
    private function dropTable(string $command)
    {
        $args = $this->getArgs($command);

        if ($this->checkTableName($args)) {
            if ($this->confirmAction('DROP the table "' . $args['t'] .'" ? (Y/N)') === 'Y') {
                if (DB::dropTable($args['t'])) {
                    static::ok(static::ACTION_DONE . PHP_EOL);
                } else {
                    static::fail(static::ACTION_FAIL . PHP_EOL . $this->tablePrettyPrint(DB::errorInfo()) . PHP_EOL);
                }
            } else {
                static::out(static::ACTION_CANCEL . PHP_EOL);
            }
        }
    }

    /**
     * Display the data of a table
     *
     * @param      string  $command  The commande passed by the user with its arguments
     */
    private function showTable(string $command)
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
     * @param      string  $command  The commande passed by the user with its arguments
     */
    private function descTable(string $command)
    {
        $args = $this->getArgs($command);

        if ($this->checkTableName($args)) {
            static::out($this->prettySqlResult($args['t'], DB::descTable($args['t'])) . PHP_EOL);
        }
    }

    /**
     * Init tha database with tables and data
     */
    private function init()
    {
        $this->createAllTables();
        $this->insertUserData();
        $this->insertChatData();
    }

    /**
     * Create all the entities table
     */
    private function createAllTables()
    {
        $entityManager = new EntityManager();

        foreach (DB::getAllEntites() as $entityName) {
            /**
             * @var        Entity  $entity  An entity
             */
            $entityClassNamespace = Ini::getParam('Entities', 'entitiesClassNamespace') . '\\' . $entityName;
            $entity               = new $entityClassNamespace;
            // @todo bug SQL table name with uppercase
            $tableName           = strtolower($entity->getTableName());

            if (!in_array($tableName, DB::getAllTables())) {
                static::out('Create table "' . $tableName . '"' . PHP_EOL);
                $entityManager->setEntity($entity);
                $entityManager->createEntityTable();
            } else {
                static::out('Table table "' . $tableName . '" already exists' . PHP_EOL);
            }
        }

        static::ok(static::ACTION_DONE . PHP_EOL);
    }

    /**
     * Insert users in database
     */
    private function insertUserData()
    {
        $users       = new UserCollection();
        $userManager = new UserManager(null, $users);

        static::out('Create user data' . PHP_EOL);

        // Create an admin with password = 123
        $admin = new User(array(
            'id'        => 1,
            'email'     => 'admin@websocket.com',
            'firstName' => 'Admin',
            'lastName'  => 'God',
            'pseudonym' => 'admin',
            'password'  => '$6$rounds=5000$xd8u1gm9aw8d2npq$VlV1nxc0CNsVhgtnKXPcvT.1Mzt.8ZjNQZYWeK7NOFNBy4M.3EEg9Kt4'
                           . 'WHFEogUA7xtH89UKDfp4UXHVYlIY00'
        ));

        $admin->setRight(new UserRight(array('idUser' => 1, 'webSocket' => 1, 'chatAdmin' => 1)));

        $users->add($admin);

        // Create some normal users with password = 123
        for ($i = 1; $i < 11; $i++) {
            $user = new User(array(
                'id'        => ($i + 1),
                'email'     => 'user_' . $i . '@websocket.com',
                'firstName' => 'User ' . $i,
                'lastName'  => 'Normal',
                'pseudonym' => 'User ' . $i,
                'password'  => '$6$rounds=5000$xd8u1gm9aw8d2npq$VlV1nxc0CNsVhgtnKXPcvT.1Mzt.8ZjNQZYWeK7NOFNBy4M.3EEg9Kt'
                               . '4WHFEogUA7xtH89UKDfp4UXHVYlIY00'
            ));

            $users->add($user);
        }

        if ($userManager->saveUserCollection($users)) {
            static::ok(sprintf('The followings users are inserted %s' . PHP_EOL, $users));
        } else {
            static::fail('An error occured on user collection save' . PHP_EOL);
        }
    }

    /**
     * Insert chat data in database
     */
    private function insertChatData()
    {
        $rooms       = new ChatRoomCollection();
        $chatManager = new ChatManager();

        static::out('Create chat data' . PHP_EOL);

        // Create a default chat room
        $default = new ChatRoom(array(
            'id'           => 1,
            'name'         => 'Default',
            'creator'      => 1,
            'creationDate' => date('Y-m-d H:i:s'),
            'maxUsers'     => 50
        ));

        $rooms->add($default);

        // Create some rooms some public and some with password 123
        for ($i = 1; $i < 11; $i++) {
            $room = new ChatRoom(array(
                'id'           => ($i + 1),
                'name'         => 'Room ' . $i,
                'creator'      => 1,
                'password'     => (mt_rand(0, 1) ? null : '123'),
                'creationDate' => date('Y-m-d H:i:s'),
                'maxUsers'     => 20
            ));

            $rooms->add($room);
        }

        if ($chatManager->saveChatRoomCollection($rooms)) {
            static::ok(sprintf('The followings chat rooms are inserted %s' . PHP_EOL, $rooms));
        } else {
            static::fail('An error occured on chat rooms collection save' . PHP_EOL);
        }
    }

    /**
     * Check if the table is set and if the table exists
     *
     * @param      string[]  $args   The command arguments
     *
     * @return     bool      True if the table exists else false
     */
    private function checkTableName(array $args): bool
    {
        $check = true;

        if (!isset($args['t'])) {
            static::fail('You need to specify a table name with -t parameter' . PHP_EOL);
            $check = false;
        } elseif (!in_array($args['t'], DB::getAllTables())) {
            static::fail('The table "' . $args['t'] . '" does not exist' . PHP_EOL);
            $check = false;
        }

        return $check;
    }

    /**
     * Check if the entity exists
     *
     * @param      string[]  $args   The command arguments
     *
     * @return     bool      True if the entity exists else false
     */
    private function checkEntityName(array $args): bool
    {
        $check = true;

        if (!isset($args['n'])) {
            static::fail('You need to specify an entity name with -n parameter' . PHP_EOL);
            $check = false;
        } elseif (!in_array($args['n'], DB::getAllEntites())) {
            static::fail('The entity "' . $args['n'] . '" does not exist' . PHP_EOL);
            $check = false;
        }

        return $check;
    }

    /**
     * Format the SQL result in a pretty output
     *
     * @param      string  $tableName  The table name
     * @param      array   $data       Array containing the SQL result
     *
     * @return     string  The pretty output
     */
    private function prettySqlResult(string $tableName, array $data): string
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

    /*=====================================
    =            Elasticsearch            =
    =====================================*/

    /**
     * Create an ES chat index with a version then create the mapping for this version and create an alias "chat" binded
     * on the version created
     */
    private function createElasticsearchIndex()
    {
        $config = Ini::getSectionParams('ElasticSearch');
        $index  = $config['index'] . '_v' . $config['version'];
        $client = \Elasticsearch\ClientBuilder::create()->build();

        // Create index
        try {
            $client->indices()->create([
                'index' => $index,
                'body'  => [
                    'settings' => [
                        'number_of_shards'   => $config['numberOfShards'],
                        'number_of_replicas' => $config['numberOfReplicas']
                    ]
                ]
            ]);

            static::ok('ES index created' . PHP_EOL);

        } catch (\Exception $e) {
            if ($e->getMessage() === 'index_already_exists_exception: already exists') {
                static::ok('ES index ' . $index . ' already created' . PHP_EOL);
            } else {
                static::fail('ES index ' . $index . ' not created' . PHP_EOL . $e->getMessage() . PHP_EOL);
            }
        }

        // Create mapping
        $this->createChatMessageMapping($config['index'], (int) $config['version']);

        // Bind the chat alias
        try {
            $client->indices()->putAlias(['index' => $index, 'name' => $config['index']]);
            static::ok('ES alias ' . $index . ' binded to ' . $config['index'] . PHP_EOL);

        } catch (\Exception $e) {
            static::fail(
                'ES alias ' . $index . ' not binded to ' . $config['index'] . PHP_EOL . $e->getMessage() . PHP_EOL
            );
        }
    }

    /**
     * Create the chat mapping for the type message
     *
     * @param      string  $index    The index name
     * @param      int     $version  The version number of the mapping
     */
    private function createChatMessageMapping(string $index, int $version)
    {
        $client = \Elasticsearch\ClientBuilder::create()->build();

        try {
            $client->indices()->putMapping([
                'index' => $index . '_v' . $version,
                'type'  => 'message',
                'body'  => [
                    'properties' => [
                        'pseudonym' => [
                            'type'  => 'string',
                            'index' => 'not_analyzed'
                        ],
                        'message' => [
                            'type' => 'string'
                        ],
                        'type' => [
                            'type'  => 'string',
                            'index' => 'not_analyzed'
                        ],
                        'date' => [
                            'type'  => 'date',
                            'index' => 'not_analyzed'
                        ],
                        'room' => [
                            'type'  => 'long',
                        ],
                        'userInfo' => [
                            'properties' => [
                                'id' => [
                                    'type'  => 'long',
                                ],
                                'ip' => [
                                    'type'  => 'ip',
                                ]
                            ]
                        ],
                    ]
                ]
            ]);

            static::ok('ES ' . $index . '_v' . $version . ' mapping created' . PHP_EOL);

        } catch (\Exception $e) {
            static::fail(
                'ES ' . $index . '_v' . $version . ' mapping not created' . PHP_EOL . $e->getMessage() . PHP_EOL
            );
        }
    }

    /*=====  End of Elasticsearch  ======*/
}
