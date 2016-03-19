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
 * @method UserChatRight getEntityById(string[] $entityId) {
 *      Get a chat right entity by its id
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

    /*-----  End of Magic methods  ------*/
}
