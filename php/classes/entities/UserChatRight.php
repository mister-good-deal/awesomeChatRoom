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
 * @property   int   $idUser  The user id
 * @property   int   $idRoom  The chat room id
 * @property   bool  $kick    The kick right
 * @property   bool  $ban     The ban right
 * @property   bool  $grant   The grant right
 * @property   bool  $edit    The edit right
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
            'idUser' => (int) $this->idUser,
            'idRoom' => (int) $this->idRoom,
            'kick'   => (bool) $this->kick,
            'ban'    => (bool) $this->ban,
            'grant'  => (bool) $this->grant,
            'edit'   => (bool) $this->edit
        );
    }

    /*-----  End of Magic methods  ------*/
}
