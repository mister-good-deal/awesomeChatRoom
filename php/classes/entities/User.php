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
     * @var string[] $mustDefinedFields Fields that must be defined when instanciate the User object
     */
    public static $mustDefinedFields = array('firstName', 'lastName', 'email');

    /**
     * @var string[] $pseudoBlackList List of unwanted pseudonyms
     */
    public static $pseudoBlackList = array('admin', 'connard', 'ntm');

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

    /*-----  End of Magic methods  ------*/

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

    /*=======================================
    =            Private methods            =
    =======================================*/
    
    /**
     * Check and sanitize the input field before setting the value and keep errors trace
     *
     * @param  string $columnName The column name
     * @param  mixed  $value      The new column value
     * @throws Exception          If the column name does not a exist
     * @return mixed              The sanitized value
     */
    private function validateField($columnName, $value)
    {
        $this->errors[$columnName] = array();
        $value                     = trim($value);
        $length                    = strlen($value);
        $maxLength                 = $this->getColumnMaxSize($columnName);
        $name                      = _(preg_replace('/([A-Z])/', ' ' . strtolower('$1'), $columnName));

        if (in_array($columnName, static::$mustDefinedFields) && $length === 0) {
            $this->errors[$columnName][] = _('The ' . $name . 'can\'t be empty');
        } elseif ($length > $maxLength) {
            $this->errors[$columnName][] = _('The ' . $name . ' size can\'t exceed ' . $maxLength . ' characters');
        }

        switch ($columnName) {
            case 'lastName':
                $value = ucwords(strtolower($value));
                $value = preg_replace(' ( )*/', ' ', $value);

                break;

            case 'firstName':
                $value = ucfirst(strtolower($value));
                $value = preg_replace(' ( )*(.)?/', '-' . strtoupper('$2'), $value);

                break;

            case 'pseudonym':
                if (in_array(strtolower($value), static::$pseudoBlackList)) {
                    $this->errors[$columnName][] = _('The pseudonym "' . $value . '" is not accepted');
                }

                break;
        }

        if (count($this->errors[$columnName]) === 0) {
            unset($this->errors[$columnName]);
        }

        return $value;
    }
    
    /*-----  End of Private methods  ------*/
}
