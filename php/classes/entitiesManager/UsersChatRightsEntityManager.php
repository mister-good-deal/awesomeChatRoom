<?php
/**
 * Entity manager for the entity UsersChatRights
 *
 * @category EntityManager
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use \abstracts\designPatterns\EntityManager as EntityManager;
use \classes\entities\UsersChatRights as UsersChatRights;

/**
 * Performed database action relative to the UsersChatRights entity class
 *
 * @property UsersChatRights $entity The UsersChatRights entity
 * @class UsersRightsChatEntityManager
 */
class UsersChatRightsEntityManager extends EntityManager
{
    /**
     * Constructor that can take a UsersChatRights entity as first parameter and a Collection as second parameter
     *
     * @param UsersChatRights $entity           A UsersChatRights entity object DEFAULT null
     * @param Collection      $entityCollection A colection oject DEFAULT null
     */
    public function __construct($entity = null, $entityCollection = null)
    {
        parent::__construct($entity, $entityCollection);

        if ($entity === null) {
            $this->entity = new UsersChatRights();
            
        }
    }

    public function grantAll()
    {
        $this->entity->kick     = 1;
        $this->entity->ban      = 1;
        $this->entity->grant    = 1;
        $this->entity->password = 1;
        $this->entity->rename   = 1;
        $this->entity->revoke   = 1;
        $this->saveEntity();
    }
}
