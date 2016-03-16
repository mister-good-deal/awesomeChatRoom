<?php
/**
 * ChatRoomsBan entity
 *
 * @package    Entity
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\Entity as Entity;

/**
 * ChatRoomsBan entity that extends the Entity abstact class
 *
 * @property   int     $idChatRoom  The chat room id
 * @property   string  $ip          The user banned ip
 * @property   string  $pseudonym   The user banned pseudonym
 * @property   int     $admin       The user admin id who banned the user
 * @property   string  $reason      The reason why the user got banned
 * @property   string  $date        The date when the user got banned
 */
class ChatRoomsBan extends Entity
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor
     */
    public function __construct()
    {
        parent::__construct('ChatRoomsBan');
    }

    /*-----  End of Magic methods  ------*/
}
