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
use \classes\IniManager as Ini;

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
     * @return array         The occured errors or success in a array
     */
    public function register($fields)
    {
        $success = false;
        $errors  = $this->checkMustDefinedField(array_keys($fields));

        if (count($errors) === 0) {
            $user         = new User($fields);
            $query        = 'SELECT MAX(id) FROM ' . $user->getTableName();
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
     * Connect a user with his login / password combinaison
     *
     * @param  string $login    User login
     * @param  string $password User password
     * @return array            The occured errors or success in a array
     * @todo                    Complete the method (block success if max attempt reached)
     */
    public function connect($login, $password)
    {
        $sqlMarks   = 'SELECT * FROM %s WHERE email = %s OR pseudo = %s';
        $sql        = $this->sqlFormater($query, $user->getTableName(), $login, $login);
        $userParams = DB::query($sql)->fetch();
        $now        = new \DateTime();
        $success    = false;
        $errors     = array();

        if (count($userParams) > 0) {
            $user = new User($userParams);

            if (hash_equals($userParams['password'], crypt($password, $userParams['password']))) {
                $success                 = true;
                $user->lastConnection    = $now->format('Y-m-d H:i:s');
                $user->connectionAttempt = 0;
                $user->ip                = $_SERVER['REMOTE_ADDR'];
            } else {
                if ((int) $user->connectionAttempt === -1) {
                    $lastConnectionAttempt = new \DateTime($user->lastConnectionAttempt);
                    $intervalInSec         = (int) $lastConnectionAttempt->diff($now)->format('%s');
                    $minInterval           = (int) Ini::getParam('User', 'minTimeAttempt');

                    if ($intervalInSec < $minInterval) {
                        $errors[] = _('You have to wait ' . $minInterval - $intervalInSec . ' sec to try to reconnect');
                    } else {
                        $user->connectionAttempt = 1;
                    }
                } else {
                    $user->lastConnectionAttempt = $now->format('Y-m-d H:i:s');

                    if ($user->ipAttempt === $_SERVER['REMOTE_ADDR']) {
                        $user->connectionAttempt++;
                    } else {
                        $user->connectionAttempt = 0;
                        $user->ipAttempt         = $_SERVER['REMOTE_ADDR'];
                    }
                }
            }
        }

        return array('success' => $success, 'errors' => $errors);
    }

    public function connectChat()
    {
        # code...
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
