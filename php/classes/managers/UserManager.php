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
use \classes\entitiesCollection\UserChatRightCollection as UserChatRightCollection;

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
        $this->userChatRightEntityManager = new UserChatRightEntityManager(null, $this->userEntity->getChatRight());

        if ($entity !== null) {
            $this->loadUserRights();
        }
    }

    /*=====  End of Magic Methods  ======*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Get the current user
     *
     * @return     User  The current user
     */
    public function getUser(): User
    {
        return $this->userEntity;
    }

    /**
     * Set the current user
     *
     * @param      User  $user   A user entity
     */
    public function setUser(User $user)
    {
        $this->userEntity = $user;
        $this->userEntityManager->setEntity($user);
        $this->userRightEntityManager->setEntity($user->getRight());
        $this->userChatRightEntityManager->setEntityCollection($user->getChatRight());
    }

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
            $response['user']['chatRight'] = $this->userEntity->getChatRight()->getCollection();
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
     * Check if a user has the right to kick a user
     *
     * @param      int   $roomId  The room ID where the user wants to kick someone
     *
     * @return     bool  True if a user has the right to kick a user from a room else false
     */
    public function hasChatKickRight(int $roomId): bool
    {
        return (
            $this->userEntityManager->checkSecurityToken() && (
                $this->userEntity->getRight()->chatAdmin || (
                    $this->userEntity->getChatRight()->getEntityById($roomId) !== null &&
                    $this->userEntity->getChatRight()->getEntityById($roomId)->kick
                )
            )
        );
    }

    /**
     * Get a user pseudonym
     *
     * @return     string  The user pseudonym (first name + last name if not defined)
     */
    public function getPseudonymForChat(): string
    {
        return $this->userEntityManager->getPseudonymForChat();
    }

    /**
     * Add a user chat right
     *
     * @param      UserChatRight  $userChatRight  The user chat right entity
     * @param      bool           $grantAll       If all the user chat right should be granted DEFAULT false
     *
     * @return     bool           True if the add succeed else false
     */
    public function addUserChatRight(UserChatRight $userChatRight, bool $grantAll = false): bool
    {
        $this->userChatRightEntityManager->setEntity($userChatRight);

        if ($grantAll) {
            $this->userChatRightEntityManager->grantAll();
        }

        $success = $this->userChatRightEntityManager->saveEntity();

        if ($success) {
            $this->userEntity->getChatRight()->add($userChatRight);
        }

        return $success;
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
            $this->userChatRightEntityManager->loadUserChatRight((int) $this->userEntity->id);
            $this->userEntity->setChatRight($this->userChatRightEntityManager->getEntityCollection());
        }
    }

    /*=====  End of Private methods  ======*/
}
