<?php
/**
 * Manager for he entity User
 *
 * @package    Manager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\entitiesManager;

use \abstracts\Manager as Manager;
use \classes\entities\User as User;
use \classes\entitiesManager\UserEntityManager as UserEntityManager;
use \classes\entitiesManager\UsersRightsEntityManager as UsersRightsEntityManager;
use \classes\entitiesManager\UsersChatRightsEntityManager as UsersChatRightsEntityManager;

/**
 * Perform action relative to the User, UserRight and UserChatRight entities classes
 */
class UserManager extends Manager
{
    /**
     * @var        UserEntityManager  $usersEntityManager   A user entity manager
     */
    private $usersEntityManager;
    /**
     * @var        UsersRightsEntityManager  $usersRightsEntityManager  A user rights entity manager
     */
    private $usersRightsEntityManager;
    /**
     * @var        UsersChatRightsEntityManager  $usersChatRightsEntityManager  A user chat rights entity manager
     */
    private $usersChatRightsEntityManager;

    /*=====================================
    =            Magic Methods            =
    =====================================*/

    /**
     * Constructor that can take a User entity as first parameter and a Collection as second parameter
     *
     * @param      User        $entity      A user entity object DEFAULT null
     * @param      Collection  $collection  A colection oject DEFAULT null
     */
    public function __construct(User $entity = null, Collection $collection = null)
    {
        parent::__construct();

        $this->usersEntityManager           = new UserEntityManager($entity, $collection);
        $this->usersRightsEntityManager     = new UsersRightsEntityManager();
        $this->usersChatRightsEntityManager = new UsersChatRightsEntityManager();

        if ($entity !== null) {
            $this->loadEntitesManager($entity->id);
        }
    }

    /*=====  End of Magic Methods  ======*/

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
        return $this->usersEntityManager->getUserIdByPseudonym($pseudonym);
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
        return $this->usersEntityManager->register($inputs);
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
        $response = $this->usersEntityManager->connect($inputs);

        if ($response['success']) {
            $this->loadEntitesManager($entity->id);
            $response['user']['chatRights'] = $this->usersChatRightsEntityManager->__toArray();
            $response['user']['rights'] = $this->usersRightsEntityManager->__toArray();
        }

        return $response;
    }

    /**
     * Check if a user have the admin access to the WebSocker server
     *
     * @return     bool  True if the User has the right else false
     */
    public function hasWebSocketServerRight(): bool
    {
        return (
            $this->usersEntityManager->checkSecurityToken() &&
            (bool) $this->usersRightsEntityManager->getEntity()->webSocket
        );
    }

    /**
     * Check if a user has the right to ckick a user
     *
     * @return     bool  True if a user has the right to kick a player from a room else false
     */
    public function hasChatAdminRight(): bool
    {
        return (
            $this->usersEntityManager->checkSecurityToken() &&
            (bool) $this->usersRightsEntityManager->getEntity()->chatAdmin
        );
    }

    /**
     * Get a user pseudonym
     *
     * @return     string  The user pseudonym (first name + last name if not defined)
     */
    public function getPseudonymForChat(): string
    {
        return $this->usersEntityManager->getUserIdByPseudonym();
    }

    /*=====  End of Public methods  ======*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Load the user chat rights and user right entities manager
     *
     * @param      int   $id     The user ID
     */
    private function loadEntitesManager(int $id)
    {
        $this->usersRightsEntityManager->loadEntity($id);
        $this->usersChatRightsEntityManager->loadEntity($id);
    }

    /*=====  End of Private methods  ======*/
}
