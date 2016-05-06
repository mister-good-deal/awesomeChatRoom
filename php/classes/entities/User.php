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
use \classes\entitiesCollection\UserChatRightCollection as UserChatRightCollection;

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
 *
 * @todo Remove null values on right and chatRight
 * @todo PHP7 defines object return OR null with method(...): ?Class
 * @see https://wiki.php.net/rfc/nullable_types
 * @see https://wiki.php.net/rfc/union_types
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
     * @var        UserChatRightCollection  $chatRight  The user chat right collection
     */
    private $chatRight = null;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor and affect values if values are passed
     *
     * @param      array  $data   Array($columnName => $value) pairs to set the object DEFAULT null
     */
    public function __construct(array $data = null)
    {
        parent::__construct('User');

        if ($data !== null) {
            $this->setAttributes($data);
        }
    }

    /**
     * Pretty output the User entity
     *
     * @return     string  The pretty output User entity
     */
    public function __toString(): string
    {
        return parent::__toString() . PHP_EOL . $this->right . PHP_EOL . $this->chatRight;
    }

    /**
     * Return the user entity in an array format
     *
     * @return     array  Array with all users attributes
     */
    public function __toArray(): array
    {
        $user = parent::__toArray();
        $user['right']     = ($this->right !== null ? $this->right->__toArray() : []);
        $user['chatRight'] = ($this->chatRight !== null ? $this->chatRight->__toArray() : []);

        return $user;
    }

    /*-----  End of Magic methods  ------*/

    /*=========================================
    =            Setters / getters            =
    =========================================*/

    /**
     * Get the user right
     *
     * @return     UserRight|null  The user right entity
     */
    public function getRight()
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
     * Get the user chat right collection
     *
     * @return     UserChatRightCollection|null  The user chat right collection
     */
    public function getChatRight()
    {
        return $this->chatRight;
    }

    /**
     * Set the user chat right collection
     *
     * @param      UserChatRightCollection  $chatRight  The user chat right collection
     */
    public function setChatRight(UserChatRightCollection $chatRight)
    {
        $this->chatRight = $chatRight;
    }

    /*-----  End of Setters / getters  ------*/
}
