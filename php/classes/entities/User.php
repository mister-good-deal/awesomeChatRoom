<?php
/**
 * User entity
 *
 * @category Entity
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\designPatterns\Entity as Entity;

/**
 * User entity that extends the Entity abstact class
 *
 * @property integer $id The user id
 * @property string  $name The user name
 * @property string  $email The user email
 *
 * @class User
 */
class User extends Entity
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor
     */
    public function __construct()
    {
        parent::__construct('User');
    }

    /*-----  End of Magic methods  ------*/
}
