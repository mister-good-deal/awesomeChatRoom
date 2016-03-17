<?php
/**
 * Entity manager for he entity User
 *
 * @package    EntityManager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use \abstracts\EntityManager as EntityManager;
use \classes\entities\User as User;
use \classes\DataBase as DB;
use \classes\IniManager as Ini;

/**
 * Performed database action relative to the User entity class
 *
 * @property   User  $entity  The user entity
 */
class UserEntityManager extends EntityManager
{
    use \traits\FiltersTrait;

    /**
     * @var        array  $params   An array containing User params in conf.ini
     */
    private $params;

    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor that can take a User entity as first parameter and a Collection as second parameter
     *
     * @param      User        $entity            A user entity object DEFAULT null
     * @param      Collection  $entityCollection  A colection oject DEFAULT null
     */
    public function __construct(User $entity = null, Collection $entityCollection = null)
    {
        parent::__construct($entity, $entityCollection);

        if ($entity === null) {
            $this->entity = new User();
        }

        Ini::setIniFileName('conf.ini');
        $this->params = Ini::getSectionParams('User');
    }

    /*=====  End of Magic methods  ======*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Get a user id by his pseudonym
     *
     * @param      string  $pseudonym  The user pseudonym
     *
     * @return     int     The user id
     */
    public function getUserIdByPseudonym(string $pseudonym): int
    {
        $user       = new User();
        $sqlMarks   = 'SELECT id FROM %s WHERE pseudonym = %s';
        $sql        = static::sqlFormater($sqlMarks, $user->getTableName(), DB::quote($pseudonym));

        return (int) DB::query($sql)->fetchColumn();
    }

    /**
     * Register a user and return errors if errors occured
     *
     * @param      array  $inputs  The user inputs in an array($columnName => $value) pairs to set the object
     *
     * @return     array  The occured errors or success in a array
     */
    public function register(array $inputs): array
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

        return array('success' => $success, 'errors' => $errors, 'user' => $user->__toArray());
    }

    /**
     * Connect a user with his login / password combinaison
     *
     * @param      string[]  $inputs  Inputs array containing array('login' => 'login', 'password' => 'password')
     *
     * @return     array  The occured errors or success in a array
     * @todo       refacto make it shorter...
     */
    public function connect(array $inputs): array
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
                    $minInterval           = (int) $this->params['minTimeAttempt'];

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
                    if ($user->connectionAttempt === (int) $this->params['maxFailConnectAttempt']) {
                        $user->connectionAttempt = -1;
                    }
                } else {
                    $user->connectionAttempt = 1;
                    $user->ipAttempt         = $_SERVER['REMOTE_ADDR'];
                }

                if ($continue) {
                    // Connection success
                    if (hash_equals($userParams['password'], crypt($password, $userParams['password']))) {
                        $success                    = true;
                        $user->password             = $password;
                        $user->lastConnection       = $now->format('Y-m-d H:i:s');
                        $user->connectionAttempt    = 0;
                        $user->ip                   = $_SERVER['REMOTE_ADDR'];
                        $user->securityToken        = bin2hex(random_bytes($params['securityTokenLength']));
                        $user->securityTokenExpires = $now->add(
                            new \DateInterval('PT' . $this->params['securityTokenDuration'] . 'S')
                        )->format('Y-m-d H:i:s');
                    } else {
                        $errors['password'] = _('Incorrect password');
                    }
                }

                $this->saveEntity($user);
            } else {
                $errors['login'] = _('This login does not exist');
            }
        }

        return array('success' => $success, 'errors' => $errors);;
    }

    /**
     * Get a user pseudonym
     *
     * @return     string  The user pseudonym (first name + last name if not defined)
     */
    public function getPseudonymForChat(): string
    {
        if ($this->entity->pseudonym !== '' && $this->entity->pseudonym !== null) {
            $pseudonym = $this->entity->pseudonym;
        } else {
            $pseudonym = $this->entity->firstName . ' ' . $this->entity->lastName;
        }

        return $pseudonym;
    }

    /**
     * Check the user security token
     *
     * @return     bool  True if the check is ok else false
     */
    public function checkSecurityToken(): bool
    {
        $sqlMarks = 'SELECT securityToken, securityTokenExpires FROM %s WHERE id = %d';
        $sql      = static::sqlFormater($sqlMarks, $user->getTableName(), $this->entity->id);
        $results  = DB::query($sql)->fetch();

        return (
            $this->entity->securityToken === $results['securityToken'] &&
            new \DateTime() <= new \DateTime($results['securityTokenExpires'])
        );
    }

    /*=====  End of Public methods  ======*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Check if a pseudonym exists in the database
     *
     * @param      string  $pseudonym  The pseudonym
     *
     * @return     bool    True if the pseudonym exists else false
     */
    private function isPseudonymExist(string $pseudonym): bool
    {
        $user     = new User();
        $sqlMarks = 'SELECT count(*) FROM %s WHERE pseudonym = %s';
        $sql      = static::sqlFormater($sqlMarks, $user->getTableName(), DB::quote($pseudonym));

        return (int) DB::query($sql)->fetchColumn() > 0;
    }

    /**
     * Check all the must defined fields and fill an errors array if not
     *
     * @param      array  $fields  The fields to check
     *
     * @return     array  The errors array with any missing must defined fields
     */
    private function checkMustDefinedField(array $fields): array
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

    /*=====  End of Private methods  ======*/
}
