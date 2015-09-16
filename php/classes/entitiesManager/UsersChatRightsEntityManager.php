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
use \classes\DataBase as DB;

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

    /**
     * Grant all the rights to the user in the current chat room
     */
    public function grantAll()
    {
        $this->entity->kick     = 1;
        $this->entity->ban      = 1;
        $this->entity->grant    = 1;
        $this->entity->password = 1;
        $this->entity->rename   = 1;
        $this->saveEntity();
    }

    /**
     * Get all the chat rooms rights for a user
     *
     * @param  integer $idUser The user id
     * @return array           The user chat rooms rights indexed by room names
     */
    public function getAllUserChatRights($idUser)
    {
        $sqlMarks        = 'SELECT `roomName`, `kick`, `ban`, `grant`, `rename`, `password` FROM %s WHERE idUser = %s';
        $sql             = static::sqlFormater($sqlMarks, $this->entity->getTableName(), DB::quote($idUser));

        return DB::query($sql)->fetchIndexedByFirstColumn();
    }
}
