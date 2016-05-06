<?php
/**
 * User Collection
 *
 * @package    CollectionEntity
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesCollection;

use abstracts\CollectionEntity as CollectionEntity;

/**
 * A collection of User entity that extends the CollectionEntity pattern
 */
class UserCollection extends CollectionEntity
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
