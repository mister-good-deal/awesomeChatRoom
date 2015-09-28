<?php
/**
 * User Collection
 *
 * @category Collection
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesCollection;

use \abstracts\designPatterns\Collection as Collection;

/**
 * A collection of User entity that extends the Colelction pattern
 *
 * @class UserCollection
 */
class UserCollection extends Collection
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
