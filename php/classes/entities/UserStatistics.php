<?php
/**
 * UserStatistics entity
 *
 * @category Entity
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\designPatterns\Entity as Entity;

/**
 * UserStatistics entity that extends the Entity abstact class
 *
 * @class UserStatistics
 */
class UserStatistics extends Entity
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor
     */
    public function __construct()
    {
        parent::__construct('UserStatistics');
    }

    /*-----  End of Magic methods  ------*/
}
