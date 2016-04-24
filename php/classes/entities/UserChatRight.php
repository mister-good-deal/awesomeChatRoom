<?php
/**
 * UserChatRight entity
 *
 * @package    Entity
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\Entity as Entity;

/**
 * UserChatRight entity that extends the Entity abstact class
 *
 * @property   int     $idUser    The user id
 * @property   int     $idRoom    The chat room id
 * @property   bool    $kick      The kick right
 * @property   bool    $ban       The ban right
 * @property   bool    $grant     The grant right
 * @property   bool    $rename    The rename right
 * @property   bool    $password  The change password right
 */
class UserChatRight extends Entity
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor and affect values if values are passed
     *
     * @param      array  $data   Array($columnName => $value) pairs to set the object DEFAULT null
     */
    public function __construct($data = null)
    {
        parent::__construct('UserChatRight');

        if ($data !== null) {
            $this->setAttributes($data);
        }
    }

    /**
     * To array overriden to handle boolean cast type
     *
     * @return     array  Array with columns name on keys and columns value on values
     *
     * @todo       See if boolean cast conversation can be done automatically
     */
    public function __toArray(): array
    {
        return array(
            'idUser'   => $this->idUser,
            'idRoom'   => $this->idRoom,
            'kick'     => (bool) $this->kick,
            'ban'      => (bool) $this->ban,
            'grant'    => (bool) $this->grant,
            'rename'   => (bool) $this->rename,
            'password' => (bool) $this->password
        );
    }

    /*-----  End of Magic methods  ------*/
}
