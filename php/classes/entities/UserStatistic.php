<?php
/**
 * UserStatistic entity
 *
 * @package    Entity
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\Entity as Entity;

/**
 * UserStatistic entity that extends the Entity abstact class
 */
class UserStatistic extends Entity
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor
     */
    public function __construct()
    {
        parent::__construct('UserStatistic');
    }

    /*-----  End of Magic methods  ------*/
}
