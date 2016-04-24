<?php
/**
 * ChatRoomBan entity
 *
 * @package    Entity
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\Entity as Entity;

/**
 * ChatRoomBan entity that extends the Entity abstact class
 *
 * @property   int     $idChatRoom  The chat room id
 * @property   string  $ip          The user banned ip
 * @property   string  $pseudonym   The user banned pseudonym
 * @property   int     $admin       The user admin id who banned the user
 * @property   string  $reason      The reason why the user got banned
 * @property   string  $date        The date when the user got banned
 */
class ChatRoomBan extends Entity
{
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
        parent::__construct('ChatRoomBan');

        if ($data !== null) {
            $this->setAttributes($data);
        }
    }

    /*-----  End of Magic methods  ------*/
}
