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
    use \traits\FiltersTrait;

    /**
     * Register a user and return errors if errors occured
     *
     * @param  array $inputs The user inputs in an array($columnName => $value) pairs to set the object
     * @return array         The occured errors or success in a array
     */
    public function register($inputs)
    {
        $success = false;
        $errors  = $this->checkMustDefinedField(array_keys($inputs));

        if (count($errors['SERVER']) === 0) {
            $user     = new User();
            $query    = 'SELECT MAX(id) FROM ' . $user->getTableName();
            $user->id = DB::query($query)->fetchColumn() + 1;
            
            $user->bindInputs($inputs);
            $errors = $user->getErrors();

            if (count($errors) === 0) {
                $success = $this->saveEntity($user);
            }
        }

        return array('success' => $success, 'errors' => $errors);
    }

    /**
     * Connect a user with his login / password combinaison
     *
     * @param  string[] $inputs Inputs array containing array('login' => 'login', 'password' => 'password')
     * @return array            The occured errors or success in a array
     * @todo                    refacto make it shorter...
     */
    public function connect($inputs)
    {
        $errors   = array();
        $success  = false;
        $login    = @$this->getInput($inputs['login']);
        $password = @$this->getInput($inputs['password']);

        if ($login === null || $login === '') {
            $errors['login'] = _('Login can\'t be empty');
        } else {
            $login = DB::quote($login);
        }

        if ($password === null || $password === '') {
            $errors['password'] = _('Password can\'t be empty');
        }

        if (count($errors) === 0) {
            $user       = new User();
            $sqlMarks   = 'SELECT * FROM %s WHERE email = %s OR pseudonym = %s';
            $sql        = static::sqlFormater($sqlMarks, $user->getTableName(), $login, $login);
            $userParams = DB::query($sql)->fetch();
            $now        = new \DateTime();
            $continue   = true;

            if ($userParams !== false) {
                $user->setAttributes($userParams);

                if ((int) $user->connectionAttempt === -1) {
                    $lastConnectionAttempt = new \DateTime($user->lastConnectionAttempt);
                    $intervalInSec         = $this->dateIntervalToSec($now->diff($lastConnectionAttempt));
                    $minInterval           = (int) Ini::getParam('User', 'minTimeAttempt');

                    if ($intervalInSec < $minInterval) {
                        $continue         = false;
                        $errors['SERVER'] = _(
                            'You have to wait ' . ($minInterval - $intervalInSec) . ' sec before trying to reconnect'
                        );
                    } else {
                        $user->connectionAttempt = 1;
                    }
                } else {
                    $user->connectionAttempt++;
                    $user->ipAttempt             = $_SERVER['REMOTE_ADDR'];
                    $user->lastConnectionAttempt = $now->format('Y-m-d H:i:s');
                }

                if ($user->ipAttempt === $_SERVER['REMOTE_ADDR']) {
                    if ($user->connectionAttempt === (int) Ini::getParam('User', 'maxFailConnectAttempt')) {
                        $user->connectionAttempt = -1;
                    }
                } else {
                    $user->connectionAttempt = 1;
                    $user->ipAttempt         = $_SERVER['REMOTE_ADDR'];
                }

                if ($continue) {
                    if (hash_equals($userParams['password'], crypt($password, $userParams['password']))) {
                        $success                 = true;
                        $user->lastConnection    = $now->format('Y-m-d H:i:s');
                        $user->connectionAttempt = 0;
                        $user->ip                = $_SERVER['REMOTE_ADDR'];
                    } else {
                        $errors['password'] = _('Incorrect password');
                    }
                }

                $this->saveEntity($user);
            } else {
                $errors['login'] = _('This login does not exist');
            }
        }

        $response = array('success' => $success, 'errors' => $errors);
        
        if ($success) {
            $user->password = $password;
            $response['user'] = $user->__toArray();
        }

        return $response;
    }

    /**
     * Authenticate a User by his login / password combinaison and return the User object on success or false on fail
     *
     * @param  string $login    The user login (email or pseudonym)
     * @param  string $password The user password
     * @return User|false       The User instanciated object or false is the authentication failed
     */
    public function authenticateUser($login, $password)
    {
        $user       = new User();
        $login      = DB::quote($login);
        $sqlMarks   = 'SELECT * FROM %s WHERE email = %s OR pseudonym = %s';
        $sql        = static::sqlFormater($sqlMarks, $user->getTableName(), $login, $login);
        $userParams = DB::query($sql)->fetch();

        if ($userParams !== false) {
            if (!hash_equals($userParams['password'], crypt($password, $userParams['password']))) {
                $user = false;
            } else {
                $user->setAttributes($userParams);
            }
        } else {
            $user = false;
        }

        return $user;
    }

    /*====================================
    =            Chat section            =
    ====================================*/
    
    /**
     * Check if a user have the admin access to the WebSocker server
     *
     * @param  string  $login    The user login
     * @param  string  $password The user password
     * @return boolean           True if the User has the right else false
     */
    public function connectWebSocketServer($login, $password)
    {
        $success = false;
        $user    = $this->authenticateUser($login, $password);

        if ($user !== false) {
            $rights = $this->getRights($user);

            if ((int) $rights['webSocket'] === 1) {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * Check if a user has the right to ckick a user
     *
     * @param  string  $login    The user login
     * @param  string  $password The user password
     * @return boolean           True if a user has the right to kick a player from a room else false
     */
    public function hasChatAdminRight($login, $password)
    {
        $success = false;
        $user    = $this->authenticateUser($login, $password);

        if ($user !== false) {
            $rights = $this->getRights($user);

            if ((int) $rights['chatAdmin'] === 1) {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * Get a user pseudonym
     *
     * @return string The user pseudonym (first name + last name if not defined)
     */
    public function getPseudonymForChat()
    {
        if ($this->entity->pseudonym !== '' && $this->entity->pseudonym !== null) {
            $pseudonym = $this->entity->pseudonym;
        } else {
            $pseudonym = $this->entity->firstName . ' ' . $this->entity->lastName;
        }

        return $pseudonym;
    }

    /**
     * Check if a pseudonym exists in the database
     *
     * @param  string  $pseudonym The pseudonym
     * @return boolean            True if the pseudonym exists else false
     */
    public function isPseudonymExist($pseudonym)
    {
        $user     = new User();
        $sqlMarks = 'SELECT count(*) FROM %s WHERE pseudonym = %s';
        $sql      = static::sqlFormater($sqlMarks, $user->getTableName(), DB::quote($pseudonym));

        return (int) DB::query($sql)->fetchColumn() > 0;
    }

    /**
     * Get all the user right
     *
     * @param  User $user The User instanciated object
     * @return array      The user rights in a array (1 = ok and 0 = nok)
     */
    private function getRights($user)
    {
        $sql = 'SELECT * FROM UsersRights WHERE idUser = ' . DB::quote($user->id);

        return $userParams = DB::query($sql)->fetch();
    }
    
    /*=====  End of Chat section  ======*/

    /**
     * Check all the must defined fields and fill an errors array if not
     *
     * @param  array $fields The fields to check
     * @return array         The errors array with any missing must defined fields
     */
    private function checkMustDefinedField($fields)
    {
        $errors           = array();
        $errors['SERVER'] = array();

        foreach (User::$mustDefinedFields as $field) {
            if (!in_array($field, $fields)) {
                $errors['SERVER'][] = _($field . ' must be defined');
            }
        }

        return $errors;
    }
}
