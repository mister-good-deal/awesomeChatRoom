<?php
/**
 * UsersRights entity
 *
 * @package    Entity
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\Entity as Entity;

/**
 * UsersRights entity that extends the Entity abstact class
 *
 * @property   int   $idUser     The user id
 * @property   bool  $webSocket  The user webSocket right
 * @property   bool  $chatAdmin  The user chatAdmin right
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
