<?php
/**
 * Manager pattern abstract class
 *
 * @package    Abstract
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace abstracts;

use \abstracts\Entity as Entity;
use \abstracts\EntityManager as EntityManager;
use \abstracts\Collection as Collection;

/**
 * Abstract Manager pattern
 *
 * @abstract
 */
abstract class Manager
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Basic constructor
     */
    public function __construct()
    {
    }

    /*=====  End of Magic methods  ======*/
}
