<?php
/**
 * Manager for he entity User
 *
 * @package    Manager
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes\managers;

use \abstracts\Manager as Manager;
use \classes\entities\User as User;
use \classes\entitiesManager\UserEntityManager as UserEntityManager;
use \classes\entitiesManager\UserRightEntityManager as UserRightEntityManager;
use \classes\entitiesManager\UserChatRightEntityManager as UserChatRightEntityManager;

/**
 * Perform action relative to the User, UserRight and UserChatRight entities classes
 */
class UserManager extends Manager
{
    /**
     * @var        User  $userEntity    A user entity
     */
    private $userEntity;
    /**
     * @var        UserEntityManager  $userEntityManager    A user entity manager
     */
    private $userEntityManager;
    /**
     * @var        UserRightEntityManager  $userRightEntityManager  A user right entity manager
     */
    private $userRightEntityManager;
    /**
     * @var        UserChatRightEntityManager  $userChatRightEntityManager  A user chat right entity manager
     */
    private $userChatRightEntityManager;

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

        $this->userEntityManager          = new UserEntityManager($entity, $collection);
        $this->userEntity                 = $this->userEntityManager->getEntity();
        $this->userRightEntityManager     = new UserRightEntityManager($this->userEntity->getRight());
        $this->userChatRightEntityManager = new UserChatRightEntityManager($this->userEntity->getChatRight());
        $this->loadUserRights();
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
        return $this->userEntityManager->getUserIdByPseudonym($pseudonym);
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
        return $this->userEntityManager->register($inputs);
    }

    /**
     * Connect a user with his login / password combinaison
     *
     * @param      string[]  $inputs  Inputs array containing array('login' => 'login', 'password' => 'password')
     *
     * @return     array  The occured errors or success in a array
     */
    public function connect(array $inputs): array
    {
        $response = $this->userEntityManager->connect($inputs);

        if ($response['success']) {
            $this->userEntity = $this->userEntityManager->getEntity();
            $this->loadUserRights();
            $response['user']['chatRight'] = $this->userEntity->getChatRight()->__toArray();
            $response['user']['right']     = $this->userEntity->getRight()->__toArray();
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
        return $this->userEntityManager->checkSecurityToken() && $this->userEntity->getRight()->webSocket;
    }

    /**
     * Check if a user has the right to ckick a user
     *
     * @return     bool  True if a user has the right to kick a player from a room else false
     */
    public function hasChatAdminRight(): bool
    {
        return $this->userEntityManager->checkSecurityToken() && $this->userEntity->getRight()->chatAdmin;
    }

    /**
     * Get a user pseudonym
     *
     * @return     string  The user pseudonym (first name + last name if not defined)
     */
    public function getPseudonymForChat(): string
    {
        return $this->userEntityManager->getUserIdByPseudonym();
    }

    /*=====  End of Public methods  ======*/

    /*=======================================
    =            Private methods            =
    =======================================*/

    /**
     * Load the user chat right and user right entities manager and put them in the user entity
     */
    private function loadUserRights()
    {
        if ($this->userEntity->getRight() === null) {
            $this->userRightEntityManager->loadEntity($this->userEntity->id);
            $this->userEntity->setRight($this->userRightEntityManager->getEntity());
        }

        if ($this->userEntity->getChatRight() === null) {
            $this->userChatRightEntityManager->loadEntity($this->userEntity->id);
            $this->userEntity->setChatRight($this->userChatRightEntityManager->getEntity());
        }
    }

    /*=====  End of Private methods  ======*/
}
