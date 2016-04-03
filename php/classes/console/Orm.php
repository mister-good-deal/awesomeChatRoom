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
    private static $SELF_COMMANDS = [
        'tables'                                                => 'Get all the tables name',
        'entities'                                              => 'Get all the entites name',
        'entity -n entityName --clean|drop|show|desc|create'    => 'Perform action on entity table',
        'clean -t tableName'                                    => 'Delete all the row of the given table name',
        'drop -t tableName'                                     => 'Drop the given table name',
        'show -t tableName [-s startIndex -e endIndex]'         => 'Show table data between startIndex and endIndex',
        'desc -t tableName'                                     => 'Show table structure',
        'create all'                                            => 'Create all tables',
        'generate data'                                         => 'Generate default data in all tables',
        'es -i index -v sersion -s shards -r -replicas --index' => 'Create elasticsearch index',
        'es -i index -v version -t type -m mapping --mapping'   => 'Create elasticsearch mapping',
        'es -i index -a alias --aliases [--no-read --no-write]' => 'Create elasticsearch aliases',
        'es --init'                                             => 'Init ES with indexn mapping and aliases',
        'init --es --sql --all'                                 => 'Initialize mysql, ES or both with --all option'
    ];

    /**
     * @var        array  $ES_CHAT_MAPPING  The elasticsearch chat mapping
     */
    private static $ES_CHAT_MAPPING = [
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
                'type'   => 'date',
                'format' => 'epoch_millis'
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
    ];

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

            case 'es':
                $this->elasticsearchProcess($command);
                break;

            case 'init':
                $this->init($command);
                break;

            default:
                $executed = false;
                break;
        }

        parent::processCommand($command, $executed);
    }

    /**
     * Init the database with tables and data and/or init Elasticsearch
     *
     * @param      string  $command  The command passed with its arguments
     */
    private function init(string $command)
    {
        $args = $this->getArgs($command);

        if (isset($args['sql']) || isset($args['all'])) {
            $this->createAllTables();
            $this->insertUserData();
            $this->insertChatData();
        }

        if (isset($args['es']) || isset($args['all'])) {
            $this->initElasticsearch();
        }
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

    /**
     * Process the command called on ES
     *
     * @param      string  $command  The command passed with its arguments
     */
    private function elasticsearchProcess(string $command)
    {
        $args     = $this->getArgs($command);
        $index    = $args['i'] ?? '';
        $alias    = $args['a'] ?? '';
        $type     = $args['t'] ?? '';
        $mapping  = $args['m'] ?? '';
        $version  = (int) $args['v'] ?? 1;
        $shards   = (int) $args['s'] ?? 2;
        $replicas = (int) $args['r'] ?? 0;

        if (isset($args['init'])) {
            $this->initElasticsearch();
        } elseif (isset($args['index'])) {
            $this->createElasticsearchIndex($index, $version, $shards, $replicas);
        } elseif (isset($args['mapping'])) {
            $this->createElasticsearchMapping($index, $version, $type, $mapping);
        } elseif (isset($args['aliases'])) {
            $this->bindAliasesToIndex($index, $alias, !isset($args['no-read']), !isset($args['no-write']));
        }
    }

    /*===========================
    =            SQL            =
    ===========================*/

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
     *
     * @todo move to PrettyOutputTrait
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

    /*=====  End of SQL  ======*/

    /*=====================================
    =            Elasticsearch            =
    =====================================*/

    /**
     * Initialize Elasticsearch by creating index, mapping and aliases from the conf.ini file
     */
    private function initElasticsearch()
    {
        $conf = Ini::getSectionParams('ElasticSearch');

        // Create index
        $this->createElasticsearchIndex(
            $conf['index'],
            (int) $conf['version'],
            (int) $conf['numberOfShards'],
            (int) $conf['numberOfReplicas']
        );

        // Create mapping
        $this->createElasticsearchMapping($conf['index'], (int) $conf['version'], 'message', static::$ES_CHAT_MAPPING);

        // Bind aliases
        $this->bindAliasesToIndex($conf['index'] . '_v'. $conf['version'], $conf['index']);
    }
    /**
     * Create an Elasticsearch index
     *
     * @param      string  $index             The index name (without prefix or suffix)
     * @param      int     $version           The index version
     * @param      int     $numberOfShards    The number of shards
     * @param      int     $numberOfReplicas  The numbre of replicas
     */
    private function createElasticsearchIndex(string $index, int $version, int $numberOfShards, int $numberOfReplicas)
    {
        $client = \Elasticsearch\ClientBuilder::create()->build();
        $index  = $index . '_v' . $version;

        try {
            $client->indices()->create([
                'index' => $index,
                'body'  => [
                    'settings' => [
                        'number_of_shards'   => $numberOfShards,
                        'number_of_replicas' => $numberOfReplicas
                    ]
                ]
            ]);

            static::ok('ES index ' . $index . ' created' . PHP_EOL);

        } catch (\Exception $e) {
            if ($e->getMessage() === 'index_already_exists_exception: already exists') {
                static::ok('ES index ' . $index . ' already created' . PHP_EOL);
            } else {
                static::fail('ES index ' . $index . ' not created' . PHP_EOL . $e->getMessage() . PHP_EOL);
            }
        }
    }

    /**
     * Create a mapping for an index
     *
     * @param      string  $index    The index name (without prefix or suffix)
     * @param      int     $version  The version number of the mapping
     * @param      string  $type     The mapping type
     * @param      array   $mapping  The mapping structure
     */
    private function createElasticsearchMapping(string $index, int $version, string $type, array $mapping)
    {
        $client = \Elasticsearch\ClientBuilder::create()->build();

        try {
            $client->indices()->putMapping([
                'index' => $index . '_v' . $version,
                'type'  => $type,
                'body'  => $mapping
            ]);

            static::ok('ES ' . $index . '_v' . $version . ' for type ' . $type . ' mapping created' . PHP_EOL);

        } catch (\Exception $e) {
            static::fail(
                'ES ' . $index . '_v' . $version . ' mapping not created' . PHP_EOL . $e->getMessage() . PHP_EOL
            );
        }
    }

    /**
     * Bind aliases read and/or write to an index
     *
     * @param      string  $index  The index to bind the aliases to
     * @param      string  $alias  The alisases name (automatic _read and _write suffix will be added)
     * @param      bool    $read   True to bind the read alias else false DEFAULT true
     * @param      bool    $write  True to bind the write alias else false DEFAULT true
     */
    private function bindAliasesToIndex(string $index, string $alias, bool $read = true, bool $write = true)
    {
        $client = \Elasticsearch\ClientBuilder::create()->build();

        try {
            if ($read) {
                $client->indices()->putAlias(['index' => $index, 'name' => $alias . '_read']);
                static::ok('ES alias ' . $alias . '_read binded to ' . $index . PHP_EOL);
            }

            if ($write) {
                $client->indices()->putAlias(['index' => $index, 'name' => $alias . '_write']);
                static::ok('ES alias ' . $alias . '_write binded to ' . $index . PHP_EOL);
            }

        } catch (\Exception $e) {
            static::fail(
                'ES alias ' . $index . ' not binded to ' . $index . PHP_EOL . $e->getMessage() . PHP_EOL
            );
        }
    }

    /**
     * Reindex the old index into the new one
     *
     * @param      string  $index       The index name (without prefix or suffix)
     * @param      int     $oldVersion  The old version
     * @param      int     $newVersion  The new version
     * @param      string  $type        The new mapping type
     * @param      array   $newMapping  The new mapping structure
     *
     * @todo
     */
    private function reindex(string $index, int $oldVersion, int $newVersion, string $type, array $newMapping)
    {
        // - create the new index
        // - bind write alias on the new index
        // - copy data from old to new index with SCROLL and BULK
        // - bind read alias on the new index
        // - delete old index
    }

    /*=====  End of Elasticsearch  ======*/
}
