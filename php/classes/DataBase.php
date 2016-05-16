<?php
/**
 * Singleton database manager
 *
 * @package    Singleton
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 * @example    /utilities/examples/dataBase.php Basic use of this singleton
 */

namespace classes;

use classes\ExceptionManager as Exception;
use classes\IniManager as Ini;
use traits\EchoTrait as EchoTrait;
// WARNING if you change the path below, change it in the initialize method in the setAttribute call

/**
 * Singleton pattern style to handle DB connection using PDO
 *
 * PDO methods that can be called directly with the __callStatic magic method
 *
 * @method static bool beginTransaction() {
 *      Initiates a transaction
 *
 *      @return bool
 * }
 *
 * @method static bool commit() {
 *      Commits a transaction
 *
 *      @return bool
 * }
 *
 * @method static mixed errorCode() {
 *      Fetch the SQLSTATE associated with the last operation on the database handle
 * }
 *
 * @method static array errorInfo() {
 *      Fetch extended error information associated with the last operation on the database handle
 *
 *      @return array
 * }
 *
 * @method static int exec(string $statement) {
 *      Execute an SQL statement and return the number of affected rows
 *
 *      @return int|bool
 * }
 *
 * @method static mixed getAttribute(int $attribute) {
 *      Retrieve a database connection attribute
 *
 *      @return string|null
 * }
 *
 * @method static array getAvailableDrivers() {
 *      Return an array of available PDO drivers
 *
 *      @return array
 * }
 *
 * @method static bool inTransaction() {
 *      Checks if inside a transaction
 *
 *      @return bool
 * }
 *
 * @method static string lastInsertId(string $name = NULL) {
 *      Returns the ID of the last inserted row or sequence value
 *
 *      @return string
 * }
 *
 * @method static PDOStatementCustom prepare(string $statement, array $driver_options = array()) {
 *      Prepares a statement for execution and returns a statement object
 *
 *      @return PDOStatementCustom|bool
 * }
 *
 * @method static PDOStatementCustom query(string $statement) {
 *      Executes an SQL statement, returning a result set as a PDOStatementCustom object
 *
 *      @return PDOStatementCustom|bool
 * }
 *
 * @method static string quote(string $string, int $parameter_type = \PDO::PARAM_STR) {
 *      Quotes a string for use in a query
 *
 *      @return string|bool
 * }
 *
 * @method static bool rollBack() {
 *      Rolls back a transaction
 *
 *      @return bool
 * }
 *
 * @method static bool setAttribute(int $attribute , mixed $value) {
 *      Set an attribute
 *
 *      @return bool
 * }
 */
class DataBase
{
    use EchoTrait;

    /**
     * @var        \PDO  $PDO   A PDO object DEFAULT null
     */
    private static $PDO = null;
    /**
     * @var        bool  $printSql  If the SQL requests should be printed in a console DEFAULT null
     */
    private static $printSQL = null;
    /**
     * @var        string  $dsn       The Data Source Name, or DSN, contains the information required to connect to the
     *                                database
     */
    private static $dsn = '';
    /**
     * @var       string  $username  The user name for the DSN string. This parameter is optional for some PDO drivers
     */
    private static $username = '';
    /**
     * @var       string  $password  The password for the DSN string. This parameter is optional for some PDO drivers
     */
    private static $password = '';
    /**
     * @var       array   $options   A key => value array of driver-specific connection options
     */
    private static $options = array();

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * A never called constructor (can't declare it private because it's generate error)
     */
    public function __construct()
    {
    }

    /**
     * Is triggered when invoking inaccessible methods in a static context
     *
     * @param      string     $name       Name of the method being called
     * @param      array      $arguments  Enumerated array containing the parameters passed to the method called
     * @throws     Exception  If the method called is not a PDO method
     *
     * @return mixed
     *
     * @static
     * @note                  This is so powerful, we can call non static methods with a static call
     */
    public static function __callStatic(string $name, array $arguments = array())
    {
        $PDO = new \ReflectionClass('\PDO');

        static::initialize();

        if ($PDO->hasMethod($name)) {
            if (static::$printSQL && ($name === 'query' || $name === 'exec')) {
                static::out(PHP_EOL . $arguments[0] . PHP_EOL);
            }

            return call_user_func_array(array(static::$PDO, $name), $arguments);
        } else {
            throw new Exception('The method "' . $name . '" is not a PDO method', Exception::$PARAMETER);
        }
    }

    /*-----  End of Magic methods  ------*/

    /*==================================================
    =            Getters / setters (static)            =
    ==================================================*/

    /**
     * Get the printSQL value
     *
     * @return     bool  The printSQL value
     *
     * @static
     */
    public static function getPrintSQL(): bool
    {
        return static::$printSQL;
    }

    /**
     * Set the printSQL value
     *
     * @param      bool  $printSQL  The printSQL value
     *
     * @static
     */
    public static function setPrintSQL(bool $printSQL)
    {
        static::$printSQL = $printSQL;
        static::setPDOStatement();
    }

    /**
     * Set the dsn value
     *
     * @param      string  $dsn    The dsn value
     *
     * @static
     */
    public static function setDsn(string $dsn)
    {
        static::$dsn = $dsn;
    }

    /**
     * Get the dsn value
     *
     * @return     string  The dsn value
     *
     * @static
     */
    public static function getDsn(): string
    {
        return static::$dsn;
    }

    /**
     * Set the username value
     *
     * @param      string  $username  The username value
     *
     * @static
     */
    public static function setUsername(string $username)
    {
        static::$username = $username;
    }

    /**
     * Get the username value
     *
     * @return     string  The username value
     *
     * @static
     */
    public static function getUsername(): string
    {
        return static::$username;
    }

    /**
     * Set the password value
     *
     * @param      string  $password  The password value
     *
     * @static
     */
    public static function setPassword(string $password)
    {
        static::$password = $password;
    }

    /**
     * Get the password value
     *
     * @return     string  The password value
     *
     * @static
     */
    public static function getPassword(): string
    {
        return static::$password;
    }

    /**
     * Set the options value
     *
     * @param      array  $options  The options value
     *
     * @static
     */
    public static function setOptions(array $options)
    {
        static::$options = $options;
    }

    /**
     * Get the options value
     *
     * @return     array  The options value
     *
     * @static
     */
    public static function getOptions(): array
    {
        return static::$options;
    }

    /*-----  End of Getters / setters (static)  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Get a list of all existing entities
     *
     * @return     string[]  List of all existing entities
     *
     * @static
     */
    public static function getAllEntities(): array
    {
        $entities         = [];
        $currentDirectory = new \DirectoryIterator(
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'entities'
        );

        foreach ($currentDirectory as $fileInfo) {
            if (!$fileInfo->isDot() && $fileInfo->isFile()) {
                $entities[] = $fileInfo->getBasename('.ini');
            }
        }

        return $entities;
    }

    /**
     * Get all the table name of he current database
     *
     * @return     string[]  The table name as a string array converted in uppercase
     *
     * @todo       MySQL 5.5 create tables with case sensitive and MySQL 5.7 convert to lower case...
     * @static
     */
    public static function getAllTables(): array
    {
        static::initialize();

        return array_map('strtoupper', static::$PDO->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Delete all the rows of a table
     *
     * @param      string  $tableName  The table name to clean
     * @return     bool    True on success else false
     *
     * @static
     */
    public static function cleanTable($tableName): bool
    {
        static::initialize();

        return static::$PDO->exec('DELETE FROM ' . $tableName) !== false;
    }

    /**
     * Drop a table
     *
     * @param      string  $tableName  The table name to drop
     * @return     bool    True on success else false
     *
     * @static
     */
    public static function dropTable(string $tableName): bool
    {
        static::initialize();

        return static::$PDO->exec('DROP TABLE ' . $tableName) !== false;
    }

    /**
     * Show all the table data with limit default (0, 100)
     *
     * @param      string  $tableName  The table name
     * @param      int     $begin      Data start at this index DEFAULT 0
     * @param      int     $end        Data stop at this index DEFAULT 100
     * @return     array   Array containing the result
     *
     * @static
     */
    public static function showTable(string $tableName, int $begin = 0, int $end = 100): array
    {
        static::initialize();

        $sqlMarks = 'SELECT *
                     FROM %s
                     LIMIT %d, %d';

        $sql = sprintf($sqlMarks, $tableName, $begin, $end);

        return static::$PDO->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Show the table description
     *
     * @param      string  $tableName  The table name
     * @return     array   Array containing the result
     *
     * @static
     */
    public static function descTable(string $tableName): array
    {
        static::initialize();

        $sqlMarks = 'DESC %s';

        $sql = sprintf($sqlMarks, $tableName);

        return static::$PDO->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /*-----  End of Public methods  ------*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Utility method to reuse the same PDO instance at each call (work like a Singleton pattern)
     *
     * @static
     */
    private static function initialize()
    {
        Ini::setIniFileName(Ini::INI_CONF_FILE);

        if (static::$printSQL === null) {
            // Load default printSQL value
            static::$printSQL = Ini::getParam('Console', 'printSql');
        }

        try {
            if (static::$PDO === null) {
                if (static::$username !== '' && static::$password !== '') {
                    if (count(static::$options) > 0) {
                        static::$PDO = new \PDO(static::$dsn, static::$username, static::$password, static::$options);
                    } else {
                        static::$PDO = new \PDO(static::$dsn, static::$username, static::$password);
                    }
                } elseif (static::$dsn !== '') {
                    static::$PDO = new \PDO(static::$dsn);
                } else {
                    // Load default database parameters
                    $param = Ini::getSectionParams('Database');

                    static::$PDO = new \PDO($param['dsn'], $param['username'], $param['password'], $param['options']);
                }

                // Load default PDO parameters
                $params = Ini::getSectionParams('PDO');

                foreach ($params as $paramName => $paramValue) {
                    if (!is_numeric($paramValue)) {
                        $paramValue = constant('\PDO::' . $paramValue);
                    }

                    static::$PDO->setAttribute(constant('\PDO::' . $paramName), $paramValue);
                }

                static::setPDOStatement();
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Exception::$CRITICAL);
        }
    }

    /**
     * Set the PDOStatement custom class
     */
    private static function setPDOStatement()
    {
        static::$PDO->setAttribute(
            \PDO::ATTR_STATEMENT_CLASS,
            array('\classes\PDOStatementCustom', array(static::$PDO, static::$printSQL))
        );
    }

    /*-----  End of Private methods  ------*/
}
