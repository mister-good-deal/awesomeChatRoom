<?php
/**
 * User Collection
 *
 * @package    EntityCollection
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesCollection;

use abstracts\EntityCollection as EntityCollection;
use classes\entities\User as User;

/**
 * A collection of User entity that extends the EntityCollection pattern
 *
 * @method User current() {
 *      Returns the current user
 *
 *      @return User The current user
 * }
 */
class UserCollection extends EntityCollection
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * constructor
     */
    public function __construct()
    {
    }

    /*-----  End of Magic methods  ------*/
}
