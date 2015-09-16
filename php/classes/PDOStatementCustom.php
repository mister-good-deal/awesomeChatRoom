<?php
/**
 * PDOStatement custom class
 *
 * @category Custom class
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes;

/**
 * PDOStatement custom class to print sql query on demand
 *
 * {@inheritdoc}
 *
 * @class PDOStatementCustom
 */
class PDOStatementCustom extends \PDOStatement
{
    use \traits\EchoTrait;

    /**
     * @var \PDO $pdo PDO object instance
     */
    protected $pdo;
    /**
     * @var boolean $printSQL If the SQL queries should be printed or not
     */
    protected $printSQL;

    /*=====================================
    =            Magic methods            =
    =====================================*/
    
     /**
     * Constructor
     *
     * @param \PDO    $pdo      $pdo value
     * @param boolean $printSQL $printSQL value
     */
    protected function __construct($pdo, $printSQL)
    {
        $this->pdo      = $pdo;
        $this->printSQL = $printSQL;
    }
    
    /*-----  End of Magic methods  ------*/
    
    /*======================================
    =            Public methods            =
    ======================================*/
    
    /**
     * Like \PDOStatement->execute() but can print the SQL query before executes it
     *
     * {@inheritdoc}
     *
     * @param  array|null $inputParameters The inputs parameters sent to replace the "?" markers tags in the SQL request
     * @return boolean                     True if the request succeeded else false
     */
    public function execute($inputParameters = null)
    {
        if ($this->printSQL && is_array($inputParameters)) {
            $this->printQuery($inputParameters);
        }

        return parent::execute($inputParameters);
    }

    /**
     * Returns an array containing all of the remaining rows in the result set
     * 
     * @return array An associative array using the first column as the key, and the remainder as associative values
     */
    public function fetchIndexedByFirstColumn() {
        return array_map('reset', $this->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC));
    }
    
    /*-----  End of Public methods  ------*/

    /*=======================================
    =            Private methods            =
    =======================================*/
    
    /**
     * Utility method to format and print the SQL query which will be executed
     *
     * @param array $inputParameters The input parameters
     */
    private function printQuery($inputParameters)
    {
        $query = str_replace('?', '\'%s\'', $this->queryString);

        array_unshift($inputParameters, $query);

        $query = call_user_func_array('sprintf', $inputParameters);

        static::out(PHP_EOL . $query . PHP_EOL);
    }
    
    /*-----  End of Private methods  ------*/
}
