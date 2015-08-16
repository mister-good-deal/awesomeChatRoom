<?php
/**
 * User entity
 *
 * @category Entity
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\designPatterns\Entity as Entity;
use \classes\ExceptionManager as Exception;

/**
 * User entity that extends the Entity abstact class
 *
 * @property integer $id         The user id
 * @property string  $firstName  The user first name
 * @property string  $lastName   The user last name
 * @property string  $pseudonym  The user pseudonym
 * @property string  $email      The user email
 *
 * @class User
 */
class User extends Entity
{
    /**
     * @var array $errors An array containing the occured errors when fields are set
     */
    private $errors = array();

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor and affect values if values are passed
     *
     * @param array $data DEFAULT null array($columnName => $value) pairs to set the object
     */
    public function __construct($data = null)
    {
        parent::__construct('User');

        if ($data !== null) {
            foreach ($data as $columnName => $value) {
                $this->{$columnName} = $value;
            }
        }
    }

    /**
     * Set the column name
     *
     * @param  string    $columnName The column name
     * @param  mixed     $value      The new column value
     * @throws Exception             If the column name does not a exist
     */
    public function __set($columnName, $value)
    {
        $value = $this->validateField($columnName, $value);
        parent::__set($columnName, $value);
    }

    /*=========================================
    =            Setters / getters            =
    =========================================*/
    
    /**
     * @return array An array containing the occured errors when fields are set
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    /*-----  End of Setters / getters  ------*/
    
    

    /*-----  End of Magic methods  ------*/

    /*=======================================
    =            Private methods            =
    =======================================*/
    
    /**
     * Check and sanitize the input field before setting the value and keep errors trace
     *
     * @param  string $columnName The column name
     * @param  mixed  $value      The new column value
     * @return mixed              The sanitized value
     */
    private function validateField($columnName, $value)
    {
        $this->errors[$columnName] = array();

        switch ($columnName) {
            case 'lastName':
            case 'firstName':
                if (strlen($value) > 64) {
                    $this->errors[$columnName][] = _('The last / first name size can\'t exceed 64 characters');
                }

                break;

            case 'pseudonym':
                if (strlen($value) > 32) {
                    $this->errors[$columnName][] = _('The pseudonym size can\'t exceed 32 characters');
                }

                break;
        }

        if (count($this->errors[$columnName] === 0)) {
            unset($this->errors[$columnName]);
        }

        return trim($value);
    }
    
    /*-----  End of Private methods  ------*/
}
