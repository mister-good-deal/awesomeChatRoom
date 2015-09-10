<?php
/**
 * UsersChatRights entity
 *
 * @category Entity
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\designPatterns\Entity as Entity;

/**
 * UsersChatRights entity that extends the Entity abstact class
 *
 * @property integer $idUser    The user id
 * @property string  $roomName  The room name
 * @property boolean $kick      The kick right
 * @property boolean $ban       The ban right
 * @property boolean $grant     The grant right
 * @property boolean $revoke    The revoke right
 * @property boolean $rename    The rename right
 * @property boolean $password  The change password right
 *
 * @class UsersChatRights
 */
class UsersChatRights extends Entity
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor
     */
    public function __construct()
    {
        parent::__construct('UsersChatRights');
    }

    /*-----  End of Magic methods  ------*/
}
