<?php
/**
 * Entity manager for the entity UserRight
 *
 * @package    EntityManager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use abstracts\EntityManager as EntityManager;
use abstracts\EntityCollection as Collection;
use classes\entities\UserRight as UserRight;

/**
 * Performed database action relative to the UserRight entity class
 *
 * @property   UserRight  $entity  The user right entity
 *
 * @method UserRight getEntity() {
 *      Get the user right entity
 *
 *      @return UserRight The user right entity
 * }
 */
class UserRightEntityManager extends EntityManager
{
    /**
     * Constructor that can take a UserRight entity as first parameter and a Collection as second parameter
     *
     * @param      UserRight   $entity      A UserRight entity object DEFAULT null
     * @param      Collection  $collection  A collection object DEFAULT null
     */
    public function __construct(UserRight $entity = null, Collection $collection = null)
    {
        parent::__construct($entity, $collection);

        if ($entity === null) {
            $this->entity = new UserRight();
        }
    }
}
