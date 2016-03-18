<?php
/**
 * Entity manager for the entity UserRight
 *
 * @package    EntityManager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use \abstracts\EntityManager as EntityManager;
use \classes\entities\UserRight as UserRight;

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
}
