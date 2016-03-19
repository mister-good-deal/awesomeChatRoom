<?php
/**
 * Entity pattern abstract class
 *
 * @package    Abstract
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace abstracts;

use \classes\ExceptionManager as Exception;
use \classes\IniManager as Ini;
use \classes\DataBase as DB;
use \abstracts\EntityManager as EntityManager;

/**
 * Abstract Entity pattern
 *
 * @abstract
 */
abstract class Entity
{
    use \traits\PrettyOutputTrait;
    use \traits\ShortcutsTrait;

    /**
     * @const ENTITIES_CONF_PATH The path where the entities ini conf file are stored
     */
    const ENTITIES_CONF_PATH = 'database/entities/';

    /**
     * @var        array  $conf     All the ini params
     */
    private $conf;
    /**
     * @var        string  $tableName   The table entity name
     */
    private $tableName;
    /**
     * @var        string  $engine  The table engine
     */
    private $engine;
    /**
     * @var        string  $charset     The table default charset DEFAULT ''
     */
    private $charset = '';
    /**
     * @var        string  $collation   The table charset collation DEFAULT ''
     */
    private $collation = '';
    /**
     * @var        string  $comment     The table comment DEFAULT ''
     */
    private $comment = '';
    /**
     * @var        array  $constraints  The table constraints
     */
    private $constraints;
    /**
     * @var        The   entity     name
     */
    private $entityName;
    /**
     * @var        string[]  $idKey     Id key name(s)
     */
    private $idKey;

    /**
     * @var        array  $columnsValue     An associative array with column name on key and its value on value
     */
    protected $columnsValue = array();
    /**
     * @var        array  $columnsAttributes    An associative array with column name on key and column attributes on value
     */
    protected $columnsAttributes = array();

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that takes the entity name as first parameter to call the parent constructor
     *
     * @param      string  $entityName  The entity name
     */
    public function __construct(string $entityName)
    {
        Ini::setIniFileName(static::ENTITIES_CONF_PATH . $entityName . '.ini');

        $this->conf       = Ini::getAllParams();
        $this->entityName = $entityName;
        $this->parseConf();
    }

    /**
     * Tell if the column name exists
     *
     * @param      string  $columnName  The column name
     *
     * @return     bool    True if the column name exists else fale
     */
    public function __isset(string $columnName): bool
    {
        return array_key_exists($columnName, $this->columnsValue);
    }

    /**
     * Get the column name value
     *
     * @param      string     $columnName  The column name
     *
     * @throws     Exception  If the column name does not a exist
     *
     * @return     mixed      The column value
     */
    public function __get(string $columnName)
    {
        if (!$this->__isset($columnName)) {
            throw new Exception('The attribute ' . $columnName . ' is undefined', Exception::$PARAMETER);
        }

        return $this->columnsValue[$columnName];
    }

    /**
     * Set the column name
     *
     * @param      string     $columnName  The column name
     * @param      mixed      $value       The new column value
     *
     * @throws     Exception  If the column name does not a exist
     */
    public function __set(string $columnName, $value)
    {
        if (!$this->__isset($columnName)) {
            throw new Exception('The attribute ' . $columnName . ' is undefined', Exception::$PARAMETER);
        }

        $this->columnsValue[$columnName] = $value;
    }

    /**
     * Pretty output the entity
     *
     * @return     string  The pretty output entity
     */
    public function __toString(): string
    {
        $string = '['  . $this->entityName . ']' . PHP_EOL;
        $keys   = array_keys($this->columnsValue);

        foreach ($this->columnsValue as $columnName => $columnValue) {
            $string .=
                '  ' . $this->smartAlign($columnName, $keys)
                . '  ' . $this->smartAlign(
                    $this->columnsAttributes[$columnName]['type'] . '(' .
                    $this->columnsAttributes[$columnName]['size'] . ')',
                    array(
                        array_column($this->columnsAttributes, 'type'),
                        array_column($this->columnsAttributes, 'size')
                    ),
                    2
                )
                . '  = ' . $this->formatVariable($columnValue) . PHP_EOL;
        }

        return $string;
    }

    /**
     * Return the entity in an array format
     *
     * @return     array  Array with columns name on keys and columns value on values
     */
    public function __toArray(): array
    {
        return $this->columnsValue;
    }

    /**
     * Info to display when using a var_dump on the entity
     *
     * @return     array  The var_dump info
     */
    public function __debugInfo(): array
    {
        return $this->columnsValue;
    }

    /*-----  End of Magic methods  ------*/

    /*==========================================
    =            Getters and setter            =
    ==========================================*/

    /**
     * Get the key(s) id of an entity
     *
     * @return     string[]  The entity key id
     */
    public function getIdKey(): array
    {
        return $this->idKey;
    }

    /**
     * Get the id value of the entity
     *
     * @return     int[]  The id value(s)
     */
    public function getIdValue(): array
    {
        $idValue = array();

        foreach ($this->idKey as $columnName) {
            $idValue[] = $this->__get($columnName);
        }

        return $idValue;
    }

    /**
     * Get the associative array idKey => idValue
     *
     * @return     array  The associative array idKey => idValue
     */
    public function getIdKeyValue(): array
    {
        $idKeyValue = array();

        foreach ($this->idKey as $columnName) {
            $idKeyValue[$columnName] = $this->__get($columnName);
        }

        return $idKeyValue;
    }

    /**
     * Get the associative array columnName => columnValue primary keys EXCLUDED
     *
     * @return     array  The associative array columnName => columnValue primary keys EXCLUDED
     */
    public function getColumnsKeyValueNoPrimary(): array
    {
        $columnsKeyValue = array();

        foreach ($this->columnsValue as $columnName => $columnValue) {
            if (!in_array($columnName, $this->idKey)) {
                $columnsKeyValue[$columnName] = $columnValue;
            }
        }

        return $columnsKeyValue;
    }

    /**
     * Set the id value(s) of the entity (can be an array if several primary keys)
     *
     * @param      int|array  $value  The id value
     *
     * @throws     Exception  If the id is on several columns and $value is not an array
     * @throws     Exception  If the id key is not found
     *
     * @todo wtf is that
     */
    public function setIdValue($value)
    {
        if (!is_array($value) && count($this->idKey) > 1) {
            throw new Exception(
                'The id is on several columns you must passed an assosiative array with keys (' .
                implode(', ', $this->idKey) . ')',
                Exception::$PARAMETER
            );
        }

        if (is_array($value)) {
            foreach ($value as $key => $val) {
                if (!array_key_exists($key, $this->columnsValue)) {
                    throw new Exception(
                        'The keys of the assosiative array must be one of these : ' . implode(', ', $this->idKey),
                        Exception::$PARAMETER
                    );
                }

                $this->columnsValue[$key] = $val;
            }
        } else {
            $this->columnsValue[$this->idKey[0]] = $value;
        }
    }

    /**
     * Get the entity table name
     *
     * @return     string  The entity table name
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Get the entity table engine
     *
     * @return     string  The entity table engine
     */
    public function getEngine(): string
    {
        return $this->engine;
    }

    /**
     * Get the entity table default charset
     *
     * @return     string  The entity table default charset
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * Get the entity table charset collation
     *
     * @return     string  The entity table charset collation
     */
    public function getCollation(): string
    {
        return $this->collation;
    }

    /**
     * Get the entity table comment
     *
     * @return     string  The entity table comment
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Get the entity table constraints
     *
     * @return     array  The entity table constraints
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * Get the columns attributes
     *
     * @return     array  The columns attributes
     */
    public function getColumnsAttributes(): array
    {
        return $this->columnsAttributes;
    }

    /**
     * Get the columns value
     *
     * @return     array  The columns value
     */
    public function getColumnsValue(): array
    {
        return $this->columnsValue;
    }

    /**
     * Get the entity name
     *
     * @return     string  The entity name
     */
    public function getEntityName(): string
    {
        return $this->entityName;
    }

    /**
     * Get a column max size
     *
     * @param      string     $columnName  The column name
     *
     * @throws     Exception  If the column name does not a exist
     *
     * @return     int        The column max size
     */
    public function getColumnMaxSize(string $columnName): int
    {
        if (!$this->__isset($columnName)) {
            throw new Exception('The attribute ' . $columnName . ' is undefined', Exception::$PARAMETER);
        }

        return $this->columnsAttributes[$columnName]['size'];
    }

    /**
     * Set multiples attributes at once
     *
     * @param      array  $attributes  The attributes to set
     */
    public function setAttributes(array $attributes)
    {
        foreach ($attributes as $columnName => $value) {
            $this->{$columnName} = $value;
        }
    }

    /*=========================================
    =            Protected methods            =
    =========================================*/

    /**
     * Check if a column value is not already in database if the column has a unique attribute constraint
     *
     * @param      string  $columnName  The column name
     * @param      mixed   $value       The column value
     *
     * @return     bool    True if the value is already in database and the column has a unique attribute constraint
     *                     else false
     */
    protected function checkUniqueField(string $columnName, $value): bool
    {
        $alreadyInDatabase = false;

        if (strpos($this->constraints['unique'], $columnName) !== false) {
            $sqlMarks = 'SELECT count(*) FROM %s WHERE %s = ' . DB::quote($value);
            $sql      = EntityManager::sqlFormater($sqlMarks, $this->tableName, $columnName);

            $alreadyInDatabase = ((int) DB::query($sql)->fetchColumn() > 0);
        }

        return $alreadyInDatabase;
    }

    /*=====  End of Protected methods  ======*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Parse an entity conf to extract attributes
     */
    private function parseConf()
    {
        $columnsValue = array();
        $constraints  = array();

        foreach ($this->conf as $columnName => $columnAttributes) {
            if ($columnName !== 'table') {
                if (isset($columnAttributes['default'])) {
                    if ($columnAttributes['default'] === 'NULL') {
                        $columnsValue[$columnName] = null;
                    } else {
                        $columnsValue[$columnName] = $columnAttributes['default'];
                    }
                } else {
                    $columnsValue[$columnName] = '';
                }

                $columnsAttributes[$columnName] = $columnAttributes;
            } else {
                $this->tableName  = $columnAttributes['name'];
                $this->engine     = $columnAttributes['engine'];

                @static::setIfIsSet($this->charset, $columnAttributes['charSet']);
                @static::setIfIsSet($this->collation, $columnAttributes['collate']);
                @static::setIfIsSet($this->comment, $columnAttributes['comment']);
                @static::setIfIsSet($constraints['unique'], $columnAttributes['unique']);

                if (isset($columnAttributes['primary'])) {
                    $constraints['primary']            = array();
                    $constraints['primary']['name']    = key($columnAttributes['primary']);
                    $constraints['primary']['columns'] = $columnAttributes['primary'][$constraints['primary']['name']];
                }

                if (isset($columnAttributes['foreignKey'])) {
                    $names                     = array_keys($columnAttributes['foreignKey']);
                    $constraints['foreignKey'] = array();

                    foreach ($names as $name) {
                        $constraints['foreignKey'][$name]               = array();
                        $constraints['foreignKey'][$name]['name']       = $name;
                        $constraints['foreignKey'][$name]['columns']    = $columnAttributes['foreignKey'][$name];
                        $constraints['foreignKey'][$name]['tableRef']   = $columnAttributes['tableRef'][$name];
                        $constraints['foreignKey'][$name]['columnsRef'] = $columnAttributes['columnRef'][$name];
                        $constraints['foreignKey'][$name]['match']      = @$columnAttributes['match'][$name];
                        $constraints['foreignKey'][$name]['onDelete']   = @$columnAttributes['onDelete'][$name];
                        $constraints['foreignKey'][$name]['onUpdate']   = @$columnAttributes['onUpdate'][$name];
                    }
                }
            }
        }

        if (isset($constraints['primary'])) {
            $this->idKey = explode(', ', str_replace('`', '', $constraints['primary']['columns']));
        } else {
            $this->idKey = array();
        }

        $this->columnsValue      = $columnsValue;
        $this->columnsAttributes = $columnsAttributes;
        $this->constraints       = $constraints;
    }

    /*-----  End of Private methods  ------*/
}
