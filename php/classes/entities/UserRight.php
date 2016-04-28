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
 * UserRight entity that extends the Entity abstact class
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

    /**
     * To array overriden to handle boolean cast type
     *
     * @return     array  Array with columns name on keys and columns value on values
     *
     * @todo       See if boolean cast conversation can be done automatically
     */
    public function __toArray(): array
    {
        return [
            'idUser'    => (int) $this->idUser,
            'webSocket' => (bool) $this->webSocket,
            'chatAdmin' => (bool) $this->chatAdmin,
            'kibana'    => (bool) $this->kibana
        ];
    }

    /*-----  End of Magic methods  ------*/
}
