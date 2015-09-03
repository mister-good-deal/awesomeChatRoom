<?php
/**
 * UsersRights entity
 *
 * @category Entity
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\designPatterns\Entity as Entity;

/**
 * UsersRights entity that extends the Entity abstact class
 *
 * @property integer $idUser The user id
 * @property boolean $webSocket The user webSocket right
 * @property boolean $chatAdmin The user chatAdmin right
 *
 * @class UsersRights
 */
class UsersRights extends Entity
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor
     */
    public function __construct()
    {
        parent::__construct('UsersRights');
    }

    /*-----  End of Magic methods  ------*/
}
