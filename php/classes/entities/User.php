<?php
/**
 * User entity
 *
 * @package    Entity
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\Entity as Entity;
use \classes\ExceptionManager as Exception;
use \classes\IniManager as Ini;
use \classes\entities\UserRight as UserRight;
use \classes\entities\UserChatRight as UserChatRight;

/**
 * User entity that extends the Entity abstact class
 *
 * @property   int     $id                     The user id
 * @property   string  $firstName              The user first name
 * @property   string  $lastName               The user last name
 * @property   string  $pseudonym              The user pseudonym
 * @property   string  $email                  The user email
 * @property   string  $password               The user password
 * @property   string  $securityToken          The user security token
 * @property   string  $securityTokenExpires   The user security token expired date
 * @property   int     $connectionAttempt      The user number of failed connection attempt
 * @property   int     $ipAttempt              The user last ip connection attempt
 * @property   int     $ip                     The user last ip connection
 * @property   string  $lastConnectionAttempt  The user last time connection attempt
 * @property   string  $lastConnection         The user last time connection
 */
class User extends Entity
{
    /**
     * @var        string[]  $mustDefinedFields     Fields that must be defined when instanciate the User object
     */
    public static $mustDefinedFields = array('firstName', 'lastName', 'email', 'password');
    /**
     * @var        string[]  $pseudoBlackList   List of unwanted pseudonyms
     */
    public static $pseudoBlackList = array('admin', 'all', 'SERVER');
    /**
     * @var        string[]  $forbidenPseudoCharacters  List of forbidden pseudonym characters
     */
    public static $forbiddenPseudoCharacters = array(',', "'");

    /**
     * @var        UserRight  $right    The user right
     */
    private $right = null;
    /**
     * @var        UserChatRight  $chatRight    The user chat right
     */
    private $chatRight = null;
    /**
     * @var        array  $errors   An array containing the occured errors when fields are set
     */
    private $errors = array();

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor and affect values if values are passed
     *
     * @param      array  $data   DEFAULT null array($columnName => $value) pairs to set the object
     */
    public function __construct(array $data = null)
    {
        parent::__construct('User');

        if ($data !== null) {
            $this->setAttributes($data);
        }
    }

    /*-----  End of Magic methods  ------*/

    /*=========================================
    =            Setters / getters            =
    =========================================*/

    /**
     * Get the user right
     *
     * @return     UserRight  The user right entity
     */
    public function getRight(): UserRight
    {
        return $this->right;
    }

    /**
     * Set the user right
     *
     * @param      UserRight  $right  The user right entity
     */
    public function setRight(UserRight $right)
    {
        $this->right = $right;
    }

    /**
     * Get the user chat right
     *
     * @return     UserChatRight  The user chat right entity
     */
    public function getChatRight(): UserChatRight
    {
        return $this->chatRight;
    }

    /**
     * Set the user chat right
     *
     * @param      UserChatRight  $chatRight  The user chat right entity
     */
    public function setChatRight(UserChatRight $chatRight)
    {
        $this->chatRight = $chatRight;
    }

    /**
     * Get the occured errors when fields are set
     *
     * @return     array  An array containing the occured errors when fields are set
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /*-----  End of Setters / getters  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Bind user inputs to set User class attributes with inputs check
     *
     * @param      array  $inputs  The user inputs
     *
     * @todo Move it to entityManager
     */
    public function bindInputs(array $inputs)
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
     * @param      string     $columnName  The column name
     * @param      string     $value       The new column value
     *
     * @return     string  The sanitized value
     *
     * @todo Move it to entityManager
     */
    private function validateField(string $columnName, string $value): string
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
