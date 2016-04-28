<?php
/**
 * UserChatRight Collection
 *
 * @package    Collection
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesCollection;

use \abstracts\Collection as Collection;
use \classes\entities\UserChatRight as UserChatRight;

/**
 * A collection of UserChatRight entity that extends the Collection pattern
 *
 * @method add(UserChatRight $entity) {
 *      Add a chat right entity at the end of the collection
 * }
 *
 * @method UserChatRight getEntityById(int $roomId) {
 *      Get a chat right entity by the room ID
 *
 *      @return UserChatRight The chat right entity
 * }
 *
 * @method UserChatRight getEntityByIndex($index) {
 *      Get a chat right entity by its index
 *
 *      @return UserChatRight The chat right entity
 * }
 */
class UserChatRightCollection extends Collection
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

    /**
     * Return the UserChatRight collection in an array format with rooms ID as key
     *
     * @return     array  Array with all the UserChatRight attributes
     */
    public function __toArray(): array
    {
        $rights = [];

        foreach ($this->collection as $right) {
            $rights[$right->idRoom] = $right->__toArray();
        }

        return $rights;
    }

    /*-----  End of Magic methods  ------*/
}
