<?php
/**
 * UserRight entity
 *
 * @package    Entity
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entities;

use \abstracts\Entity as Entity;

/**
 * UserRight entity that extends the Entity abstract class
 *
 * @property   int   $idUser     The user id
 * @property   bool  $webSocket  The user webSocket right
 * @property   bool  $chatAdmin  The user chatAdmin right
 * @property   bool  $kibana     The user Kibana right
 */
class UserRight extends Entity
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that calls the parent Entity constructor
     *
     * @param      array  $data   Array($columnName => $value) pairs to set the object DEFAULT null
     */
    public function __construct(array $data = null)
    {
        parent::__construct('UserRight');

        if ($data !== null) {
            $this->setAttributes($data);
        }
    }

    /*-----  End of Magic methods  ------*/
}
