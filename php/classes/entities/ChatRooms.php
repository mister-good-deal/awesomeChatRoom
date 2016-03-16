<?php
/**
 * ChatRooms entity
 *
 * @package    Entity
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\Entity as Entity;

/**
 * ChatRooms entity that extends the Entity abstact class
 *
 * @property   int     $id            The chat room id
 * @property   int     $creator       The creator id user
 * @property   int     $password      The room password
 * @property   string  $creationDate  The room creation date
 * @property   int     $maxUsers      The room max users number
 */
class ChatRooms extends Entity
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor
     */
    public function __construct()
    {
        parent::__construct('ChatRooms');
    }

    /*-----  End of Magic methods  ------*/
}
