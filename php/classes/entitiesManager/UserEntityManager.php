<?php
/**
 * Entity manager for he entity User
 *
 * @category EntityManager
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use \abstracts\designPatterns\EntityManager as EntityManager;
use \classes\entities\User as User;
use \classes\DataBase as DB;

/**
 * Performed database action relative to the User entity class
 *
 * @class UserEntityManager
 */
class UserEntityManager extends EntityManager
{
    /**
     * Register a user and return errors if errors occured
     *
     * @param  array $data array($columnName => $value) pairs to set the object
     * @return array       The occured errors in a array
     */
    public function register($data)
    {
        $user         = new User($data);
        $user->id     = DB::query('SELECT MAX(' . $user->getIdKey()[0] . ') FROM ' . $user->getTableName())->fetchColumn();
        $this->entity = $user;
        $errors       = $user->getErrors();
        $success      = false;

        if (count($errors) === 0) {
            $success = $this->saveEntity();
        }

        return array('success' => $success, 'errors' => $errors);
    }
}
