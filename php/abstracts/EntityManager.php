<?php
/**
 * Entity manager pattern abstract class
 *
 * @package    Abstract
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace abstracts;

use \classes\ExceptionManager as Exception;
use \abstracts\Entity as Entity;
use \abstracts\Collection as Collection;
use \classes\DataBase as DB;
use \classes\IniManager as Ini;

/**
 * Abstract EntityManager pattern
 *
 * @abstract
 *
 * @todo PHP7 defines object return OR null with method(...): ?Class
 * @see https://wiki.php.net/rfc/nullable_types
 * @see https://wiki.php.net/rfc/union_types
 */
abstract class EntityManager
{
    /**
     * @var        Entity  $entity  An Entity object
     */
    protected $entity;
    /**
     * @var        Collection  $entityCollection    An EntityCollection object
     */
    protected $entityCollection;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that can take an Entity as first parameter and a Collection as second parameter
     *
     * @param      Entity      $entity            An entity object DEFAULT null
     * @param      Collection  $entityCollection  A EntityCollection object DEFAULT null
     */
    public function __construct(Entity $entity = null, Collection $entityCollection = null)
    {
        if ($entity !== null) {
            $this->entity = $entity;
        }

        if ($entityCollection !== null) {
            $this->entityCollection = $entityCollection;
        }
    }

    /*-----  End of Magic methods  ------*/

    /*==========================================
    =            Getters and setter            =
    ==========================================*/

    /**
     * Get the entity object
     *
     * @return     Entity|null  The entity object
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * Set the entity object
     *
     * @param      Entity  $entity  The new entity oject
     */
    public function setEntity(Entity $entity)
    {
        $this->entity = $entity;
    }

    /**
     * Get the entity collection object
     *
     * @return     Collection|null  The entity EntityCollection object
     */
    public function getEntityCollection()
    {
        return $this->entityCollection;
    }

    /**
     * Set the entity collection object
     *
     * @param      Collection  $entityCollection  The new entity collection object
     */
    public function setEntityCollection(Collection $entityCollection)
    {
        $this->entityCollection = $entityCollection;
    }

    /*-----  End of Getters and setter  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Load an entity by its id
     *
     * @param      int|array  $id     The id value
     *
     * @return     bool       True if an entity was retrieved from the database else false
     */
    public function loadEntity($id): bool
    {
        $this->entity->setIdValue($id); // @todo check this shit
        return $this->loadEntityInDatabase();
    }

    /**
     * Save the entity in the database
     *
     * @param      Entity  $entity  OPTIONAL If an entity is passed, this entity becomes the EntityManager Entity DEFAULT null
     *
     * @return     bool    True if the entity has been saved or updated else false
     */
    public function saveEntity(Entity $entity = null): bool
    {
        if ($entity !== null) {
            $this->setEntity($entity);
        }

        $sucess = true;

        if ($this->entityAlreadyExists()) {
            $sucess = $this->updateInDatabase() === 1;
        } else {
            $sucess = $this->saveInDatabase();
        }

        return $sucess;
    }

    /**
     * Save the entity EntityCollection in the database
     *
     * @param      Collection  $collection  OPTIONAL If a collection is passed, this collection becomes the
     *                                      EntityManager Collection DEFAULT null
     *
     * @return     bool        True if the entity collection has been saved else false
     */
    public function saveCollection(Collection $collection = null): bool
    {
        if ($collection !== null) {
            $this->setEntityCollection($collection);
        }

        $currentEntity = $this->entity;
        $success       = true;

        DB::beginTransaction();

        foreach ($this->entityCollection as $entity) {
            if (!$success) {
                break;
            }

            $this->setEntity($entity);
            $success = $this->saveEntity();
        }

        if ($success) {
            DB::commit();
        } else {
            DB::rollBack();
        }

        // restore the initial entity
        $this->entity = $currentEntity;

        return $success;
    }

    /**
     * Delete an entity in the database
     *
     * @return     bool  True if the entity has beed deleted else false
     */
    public function deleteEntity(): bool
    {
        return $this->deleteInDatabse();
    }

    /**
     * Drop the entity table in the database
     *
     * @throws     Exception  If the table is not dropped
     */
    public function dropEntityTable()
    {
        if (!$this->dropTable()) {
            throw new Exception(DB::errorInfo()[2], Exception::$ERROR);
        }
    }

    /**
     * Create the entity table in the database
     *
     * @throws     Exception  If the table is not created
     */
    public function createEntityTable()
    {
        if (!$this->createTable()) {
            throw new Exception(DB::errorInfo()[2], Exception::$ERROR);
        }
    }

    /**
     * Format a sql query with sprintf function First arg must be the sql string with markers (%s, %d, ...) Others args
     * should be the values for the markers
     *
     * @return     string  The SQL formated string
     *
     * @todo PHP7 arguments prototype with (...)
     */
    public static function sqlFormater(): string
    {
        return call_user_func_array('sprintf', func_get_args());
    }

    /*-----  End of Public methods  ------*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Load an entity by its id in the database
     *
     * @return     bool  True if an entity was retrieved from the database elese false
     */
    private function loadEntityInDatabase(): bool
    {
        $success  = false;
        $sqlMarks = " SELECT *\n FROM %s\n WHERE %s";

        $sql = static::sqlFormater(
            $sqlMarks,
            $this->entity->getTableName(),
            $this->getEntityPrimaryKeysWhereClause()
        );

        $attributes = DB::query($sql)->fetch();

        if ($attributes !== false) {
            $this->entity->setAttributes($attributes);
            $success = true;
        }

        return $success;
    }

    /**
     * Check if the entity already exists in the database
     *
     * @return     bool  True if the entity exists else false
     */
    private function entityAlreadyExists(): bool
    {
        $sqlMarks = " SELECT COUNT(*)\n FROM %s\n WHERE %s";

        $sql = static::sqlFormater(
            $sqlMarks,
            $this->entity->getTableName(),
            $this->getEntityPrimaryKeysWhereClause()
        );

        return ((int) DB::query($sql)->fetchColumn() >= 1);
    }

    /**
     * Save the entity in the database
     *
     * @return     bool  True if the entity has beed saved else false
     */
    private function saveInDatabase(): bool
    {
        $sqlMarks = " INSERT INTO %s\n VALUES %s";

        $sql = static::sqlFormater(
            $sqlMarks,
            $this->entity->getTableName(),
            $this->getEntityAttributesMarks($this->entity)
        );

        return DB::prepare($sql)->execute(array_values($this->entity->getColumnsValue()));
    }

    /**
     * Uddape the entity in the database
     *
     * @return     int   The number of rows updated
     */
    private function updateInDatabase(): int
    {
        $sqlMarks = " UPDATE %s\n SET %s\n WHERE %s";

        $sql = static::sqlFormater(
            $sqlMarks,
            $this->entity->getTableName(),
            $this->getEntityUpdateMarksValue(),
            $this->getEntityPrimaryKeysWhereClause()
        );

        return (int) DB::exec($sql);
    }

    /**
     * Delete the entity from the database
     *
     * @return     bool  True if the entity has beed deleted else false
     */
    private function deleteInDatabse(): bool
    {
        $sqlMarks = " DELETE FROM %s\n WHERE %s";

        $sql = static::sqlFormater(
            $sqlMarks,
            $this->entity->getTableName(),
            $this->getEntityPrimaryKeysWhereClause()
        );

        return ((int) DB::exec($sql) === 1);
    }

    /**
     * Drop the entity table
     *
     * @return     bool  True if the table is dropped else false
     */
    private function dropTable(): bool
    {
        $sql = 'DROP TABLE `' . $this->entity->getTableName() . '`;';

        return DB::exec($sql) !== false;
    }

    /**
     * Create a table based on the entity ini conf file
     *
     * @return     bool  True if the table is created else false
     */
    private function createTable(): bool
    {
        $columns     = array();
        $comment     = 'AUTO GENERATED THE ' . date('Y-m-d H:i:s');
        $sql         = 'CREATE TABLE `' . $this->entity->getTableName() . '` (';

        foreach ($this->entity->getColumnsAttributes() as $columnName => $columnAttributes) {
            $columns[] = $this->createColumnDefinition($columnName, $columnAttributes);
        }

        $sql .= implode(', ', $columns);
        $sql .= $this->createTableConstraints() . PHP_EOL;
        $sql .= ') ENGINE = ' . $this->entity->getEngine();

        if ($this->entity->getCharset() !== '') {
            $sql .= ', CHARACTER SET = ' . $this->entity->getCharset();
        }

        if ($this->entity->getCollation() !== '') {
            $sql .= ', COLLATE = ' . $this->entity->getCollation();
        }

        if ($this->entity->getComment() !== '') {
            $comment .= ' | ' . $this->entity->getComment();
        }

        $sql .= ', COMMENT = \'' . $comment . '\'';

        return DB::exec($sql . ';') !== false;
    }

    /*==========  Utilities methods  ==========*/

    /**
     * Get the "?" markers of the entity
     *
     * @return     string  The string markers (?, ?, ?)
     */
    private function getEntityAttributesMarks(): string
    {
        return '(' . implode(array_fill(0, count($this->entity->getColumnsAttributes()), '?'), ', ') . ')';
    }

    /**
     * Get the "columnName = 'columnValue'" markers of the entity for the update sql command
     *
     * @return     string  The string markers (columnName1 = 'value1', columnName2 = 'value2') primary keys EXCLUDED
     */
    private function getEntityUpdateMarksValue(): string
    {
        $marks = array();

        foreach ($this->entity->getColumnsKeyValueNoPrimary() as $columnName => $columnValue) {
            if ($columnValue === null) {
                $columnValue = 'NULL';
            } elseif (is_bool($columnValue)) {
                $columnValue = ($columnValue ? 1 : 0);
            } else {
                $columnValue = DB::quote($columnValue);
            }

            $marks[] = '`' . $columnName . '` = ' . $columnValue;
        }

        return implode(', ', $marks);
    }

    /**
     * Get the "primaryKey1 = 'primaryKey1Value' AND primaryKey2 = 'primaryKey2Value'" of the entity
     *
     * @return     string  The SQL segment string "primaryKey1 = 'primaryKey1Value' AND primaryKey2 = 'primaryKey2Value'"
     */
    private function getEntityPrimaryKeysWhereClause(): string
    {
        $columnsValue = array();

        foreach ($this->entity->getIdKeyValue() as $columnName => $columnValue) {
            if ($columnValue === null) {
                $columnValue = 'NULL';
            } else {
                $columnValue = DB::quote($columnValue);
            }

            $columnsValue[] = '`' . $columnName . '` = ' . $columnValue;
        }

        return implode($columnsValue, ' AND ');
    }

    /**
     * Utility method to set and return a column definition to put in a SQL create table query
     *
     * @param      string  $columnName        The column name
     * @param      array   $columnAttributes  The columns attributes
     *
     * @return     string  The formatted string to put in a SQL create table query
     */
    private function createColumnDefinition(string $columnName, array $columnAttributes): string
    {
        $col = PHP_EOL . "\t`" . $columnName . '` ' . $columnAttributes['type'];

        if (isset($columnAttributes['size'])) {
            $col .= '(' . $columnAttributes['size'] . ')';
        }

        if (isset($columnAttributes['unsigned'])) {
            $col .= ' UNSIGNED';
        }

        if ($columnAttributes['isNull']) {
            $col .= ' NULL';
        } else {
            $col .= ' NOT NULL';
        }

        if (isset($columnAttributes['default'])) {
            $col .= ' DEFAULT '
                . ($columnAttributes['default'] === 'NULL' ? 'NULL' : '\'' . $columnAttributes['default'] . '\'');
        }

        if (isset($columnAttributes['autoIncrement'])) {
            $col .= ' AUTO_INCREMENT';
        }

        if (isset($columnAttributes['comment'])) {
            $col .= ' COMMENT \'' . $columnAttributes['comment'] . '\'';
        }

        if (isset($columnAttributes['storage'])) {
            $col .= ' STORAGE ' . $columnAttributes['storage'];
        }

        return $col;
    }

    /**
     * Utility method to set en return the table constraints to put in a SQL create table query
     *
     * @return     string  The formatted string to put in a SQL create table query
     */
    private function createTableConstraints(): string
    {
        $constraints = $this->entity->getConstraints();
        $sql = '';

        if (isset($constraints['primary'])) {
            $sql .= ',' . PHP_EOL . "\tCONSTRAINT `" . $constraints['primary']['name'] . '`';
            $sql .= ' PRIMARY KEY (' . $constraints['primary']['columns'] . ')';
        }

        if (isset($constraints['unique'])) {
            $sql .= ',' . PHP_EOL . "\tUNIQUE `" . $this->entity->getTableName() . '_unique_constraint`';
            $sql .= ' (' . $constraints['unique'] . ')';
        }

        if (isset($constraints['foreignKey'])) {
            $names = array_keys($constraints['foreignKey']);

            foreach ($names as $name) {
                $sql .= ',' . PHP_EOL . "\tCONSTRAINT `" . $name . '`';
                $sql .= ' FOREIGN KEY (' . $constraints['foreignKey'][$name]['columns'] . ')';
                $sql .= PHP_EOL . "\t\tREFERENCES `" . $constraints['foreignKey'][$name]['tableRef'] . '`';
                $sql .= '(' . $constraints['foreignKey'][$name]['columnsRef'] . ')';

                if (isset($constraints['foreignKey'][$name]['match'])) {
                    $sql .= PHP_EOL . "\t\tMATCH " . $constraints['foreignKey'][$name]['match'];
                }

                if (isset($constraints['foreignKey'][$name]['onDelete'])) {
                    $sql .= PHP_EOL . "\t\tON DELETE " . $constraints['foreignKey'][$name]['onDelete'];
                }

                if (isset($constraints['foreignKey'][$name]['onUpdate'])) {
                    $sql .= PHP_EOL . "\t\tON UPDATE " . $constraints['foreignKey'][$name]['onUpdate'];
                }
            }
        }

        return $sql;
    }

    /*-----  End of Private methods  ------*/
}
