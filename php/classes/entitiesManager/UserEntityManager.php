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
     * @param  array $fields The user fields in an array($columnName => $value) pairs to set the object
     * @return array         The occured errors in a array
     */
    public function register($fields)
    {
        $success = false;
        $errors  = $this->checkMustDefinedField(array_keys($fields));

        if (count($errors) === 0) {
            $user         = new User($fields);
            $query        = 'SELECT MAX(' . $user->getIdKey()[0] . ') FROM ' . $user->getTableName();
            $user->id     = DB::query($query)->fetchColumn() + 1;
            $this->entity = $user;
            $errors       = $user->getErrors();

            if (count($errors) === 0) {
                $success = $this->saveEntity();
            }
        }

        return array('success' => $success, 'errors' => $errors);
    }

    /**
     * Check all the must defined fields and fill an errors array if not
     *
     * @param  array $fields The fields to check
     * @return array         The errors array with any missing must defined fields
     */
    private function checkMustDefinedField($fields)
    {
        $errors = array();

        foreach (User::$mustDefinedFields as $field) {
            if (!in_array($field, $fields)) {
                $errors[] = _($field . ' must be defined');
            }
        }

        return $errors;
    }
}
