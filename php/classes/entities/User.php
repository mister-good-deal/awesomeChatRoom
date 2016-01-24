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
use \classes\IniManager as Ini;
use \classes\entities\UsersRights as UsersRights;

/**
 * User entity that extends the Entity abstact class
 *
 * @property integer $id                    The user id
 * @property string  $firstName             The user first name
 * @property string  $lastName              The user last name
 * @property string  $pseudonym             The user pseudonym
 * @property string  $email                 The user email
 * @property string  $password              The user password
 * @property integer $connectionAttempt     The user number of failed connection attempt
 * @property integer $ipAttempt             The user last ip connection attempt
 * @property integer $ip                    The user last ip connection
 * @property string  $lastConnectionAttempt The user last time connection attempt
 * @property string  $lastConnection        The user last time connection
 *
 * @class User
 */
class User extends Entity
{
    /**
     * @var string[] $mustDefinedFields Fields that must be defined when instanciate the User object
     */
    public static $mustDefinedFields = array('firstName', 'lastName', 'email', 'password');

    /**
     * @var string[] $pseudoBlackList List of unwanted pseudonyms
     */
    public static $pseudoBlackList = array('admin', 'all', 'SERVER');

    /**
     * @var string[] $forbidenPseudoCharacters List of forbidden pseudonym characters
     */
    public static $forbiddenPseudoCharacters = array(',', "'");

    /**
     * @var array $errors An array containing the occured errors when fields are set
     */
    private $errors = array();
    /**
     * @var UsersRights $userRights The user rights
     */
    private $userRights;

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
            $this->setAttributes($data);
        }

        $this->userRights = new UsersRights();
    }

    /*-----  End of Magic methods  ------*/

    /*=========================================
    =            Setters / getters            =
    =========================================*/
    
    /**
     * Get the occured errors when fields are set
     *
     * @return array An array containing the occured errors when fields are set
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get the user rights
     *
     * @return UsersRights The user rights
     */
    public function getUserRights()
    {
        return $this->userRights;
    }

    /**
     * Set the user rights
     *
     * @param UsersRights $userRights The user rights
     */
    public function setUserRights($userRights)
    {
        $this->userRights = $userRights;
    }
    
    /*-----  End of Setters / getters  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Bind user inputs to set User class attributes with inputs check
     *
     * @param array $inputs The user inputs
     */
    public function bindInputs($inputs)
    {
        foreach ($inputs as $inputName => &$inputValue) {
            $inputValue = $this->validateField($inputName, $inputValue);
        }
        
        $this->setAttributes($inputs);
    }

    /*=====  End of Public methods  ======*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Check and sanitize the input field before setting the value and keep errors trace
     *
     * @param  string $columnName The column name
     * @param  string $value      The new column value
     * @throws Exception          If the column name does not a exist
     * @return string             The sanitized value
     */
    private function validateField($columnName, $value)
    {
        if ($columnName !== 'password') {
            $value = trim($value);
        }

        $this->errors[$columnName] = array();
        $length                    = strlen($value);
        $maxLength                 = $this->getColumnMaxSize($columnName);
        $name                      = _(strtolower(preg_replace('/([A-Z])/', ' $0', $columnName)));

        if (in_array($columnName, static::$mustDefinedFields) && $length === 0) {
            $this->errors[$columnName][] = _('The ' . $name . ' can\'t be empty');
        } elseif ($length > $maxLength) {
            $this->errors[$columnName][] = _('The ' . $name . ' size can\'t exceed ' . $maxLength . ' characters');
        }

        if ($this->checkUniqueField($columnName, $value)) {
            $this->errors[$columnName][] = _('This ' . $name . ' is already used');
        }

        switch ($columnName) {
        case 'lastName':
            $value = ucwords(strtolower($value));
            $value = preg_replace('/ ( )*/', ' ', $value);

            break;

        case 'firstName':
            $value = ucfirst(strtolower($value));
            $value = preg_replace('/ ( )*(.)?/', '-' . strtoupper('$2'), $value);

            break;

        case 'pseudonym':
            if (in_array(strtolower($value), static::$pseudoBlackList)) {
                $this->errors[$columnName][] = _('The pseudonym "' . $value . '" is not accepted');
            }

            foreach (static::$forbiddenPseudoCharacters as $forbiddenPseudoCharacter) {
                if (strpos($value, $forbiddenPseudoCharacter) !== false) {
                    $this->errors[$columnName][] = _(
                        'The character "' . $forbiddenPseudoCharacter . '" is not accepted in pseudonyms'
                    );
                }
            }

            if ($value === '') {
                $value = null;
            }

            break;

        case 'email':
            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $this->errors[$columnName][] = _('This is not a valid email address');
            }

            break;

        case 'password':
            Ini::setIniFileName(Ini::INI_CONF_FILE);
            $minPasswordLength = Ini::getParam('User', 'minPasswordLength');

            if ($length < $minPasswordLength) {
                $this->errors[$columnName][] = _('The password length must be at least ' . $minPasswordLength);
            }

            $value = crypt($value, Ini::getParam('User', 'passwordCryptSalt'));

            break;
        }

        if (count($this->errors[$columnName]) === 0) {
            unset($this->errors[$columnName]);
        }

        return $value;
    }

    /*=====  End of Private methods  ======*/
}
