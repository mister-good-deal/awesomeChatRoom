<?php
/**
 * RoomRight entity
 *
 * @package    Entity
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\Entity as Entity;

/**
 * UserRoomRight entity that extends the Entity abstact class
 *
 * @property   int   $idUser  The user ID
 * @property   int   $idRoom  The room ID
 * @property   bool  $kick    The kick right
 * @property   bool  $ban     The ban right
 * @property   bool  $grant   The grant right
 * @property   bool  $edit    The edit right
 */
class RoomRight extends Entity
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
        parent::__construct('RoomRight');

        if ($data !== null) {
            $this->setAttributes($data);
        }
    }

    /*-----  End of Magic methods  ------*/
}
