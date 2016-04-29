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
use \classes\entities\UserChatRight as UserChatRight;
use \classes\entitiesManager\UserEntityManager as UserEntityManager;
use \classes\entitiesManager\UserRightEntityManager as UserRightEntityManager;
use \classes\entitiesManager\UserChatRightEntityManager as UserChatRightEntityManager;
use \classes\entitiesCollection\UserCollection as UserCollection;
use \classes\entitiesCollection\UserChatRightCollection as UserChatRightCollection;
use \classes\LoggerManager as Logger;

/**
 * Perform action relative to the User, UserRight and UserChatRight entities classes
 *
 * @todo remove getRight and getChatRight null check
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
     * @param      User|null            $entity      A user entity object DEFAULT null
     * @param      UserCollection|null  $collection  A users collection oject DEFAULT null
     */
    public function __construct($entity = null, $collection = null)
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
     * Get a user ID by his pseudonym
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
     * Get a user pseudonym by his ID
     *
     * @param      int     $id     The user ID
     *
     * @return     string  The user pseudonym
     */
    public function getUserPseudonymById(int $id): string
    {
        return $this->userEntityManager->getUserPseudonymById($id);
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
            $response['user'] = $this->userEntity->__toArray();
            $_SESSION['user'] = serialize($this->userEntity);
        }

        return $response;
    }

    /**
     * Send an email to the user
     *
     * @param      string      $subject  The email subject
     * @param      string      $content  The email content in HTML
     */
    public function sendEmail(string $subject, string $content)
    {
        try {
            $this->userEntityManager->sendEmail($subject, $content);
        } catch (\Exception $e) {
            (new Logger())->log($e->getCode(), $e->getMessage());
        }
    }

    /**
     * Check if a user have the admin access to the WebSocker server
     *
     * @return     bool  True if the User has the right else false
     */
    public function hasWebSocketServerRight(): bool
    {
        return (
            $this->userEntityManager->checkSecurityToken() &&
            $this->userEntity->getRight() !== null &&
            $this->userEntity->getRight()->webSocket
        );
    }

    /**
     * Check if a user have the admin access to the WebSocker server
     *
     * @return     bool  True if the User has the right else false
     */
    public function hasKibanaRight(): bool
    {
        return (
            $this->userEntityManager->checkSecurityToken() &&
            $this->userEntity->getRight() !== null &&
            $this->userEntity->getRight()->kibana
        );
    }

    /**
     * Check if a user has the right to kick a user
     *
     * @param      int   $roomId  The room ID
     *
     * @return     bool  True if a user has the right to kick a user from a room else false
     */
    public function hasChatKickRight(int $roomId): bool
    {
        return (
            $this->userEntityManager->checkSecurityToken() &&
            $this->userEntity->getRight() !== null && (
                $this->userEntity->getRight()->chatAdmin || (
                    $this->userEntity->getChatRight()->getEntityById($roomId) !== null &&
                    $this->userEntity->getChatRight()->getEntityById($roomId)->kick
                )
            )
        );
    }

    /**
     * Check if a user has the right to ban a user
     *
     * @param      int   $roomId  The room ID
     *
     * @return     bool  True if a user has the right to ban a user from a room else false
     */
    public function hasChatBanRight(int $roomId): bool
    {
        return (
            $this->userEntityManager->checkSecurityToken() &&
            $this->userEntity->getRight() !== null && (
                $this->userEntity->getRight()->chatAdmin || (
                    $this->userEntity->getChatRight()->getEntityById($roomId) !== null &&
                    $this->userEntity->getChatRight()->getEntityById($roomId)->ban
                )
            )
        );
    }

    /**
     * Check if a user has the right to grant a user right in the room
     *
     * @param      int   $roomId  The room ID
     *
     * @return     bool  True if a user has the right to grant a user right in the room else false
     */
    public function hasChatGrantRight(int $roomId): bool
    {
        return (
            $this->userEntityManager->checkSecurityToken() &&
            $this->userEntity->getRight() !== null && (
                $this->userEntity->getRight()->chatAdmin || (
                    $this->userEntity->getChatRight()->getEntityById($roomId) !== null &&
                    $this->userEntity->getChatRight()->getEntityById($roomId)->grant
                )
            )
        );
    }

    /**
     * Check if a user has the right to edit the room's information
     *
     * @param      int   $roomId  The room ID
     *
     * @return     bool  True if a user has the right to edit the room's information else false
     */
    public function hasChatEditRight(int $roomId): bool
    {
        return (
            $this->userEntityManager->checkSecurityToken() &&
            $this->userEntity->getRight() !== null && (
                $this->userEntity->getRight()->chatAdmin || (
                    $this->userEntity->getChatRight()->getEntityById($roomId) !== null &&
                    $this->userEntity->getChatRight()->getEntityById($roomId)->edit
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
     * Add a user global chat right
     *
     * @param      UserChatRight  $userChatRight  The user chat right entity
     * @param      bool           $grantAll       If all the user chat right should be granted DEFAULT false
     *
     * @return     bool           True if the add succeed else false
     */
    public function addUserGlobalChatRight(UserChatRight $userChatRight, bool $grantAll = false): bool
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

    /**
     * Set one user chat right
     *
     * @param      UserChatRight  $chatRight  The user chat right to set
     *
     * @return     bool           True if the operation succeed else false
     */
    public function setUserChatRight(UserChatRight $chatRight): bool
    {
        $success = true;

        try {
            $success = $this->userChatRightEntityManager->saveEntity($chatRight);
        } catch (Exception $e) {
            $success = false;
        } finally {
            return $success;
        }
    }

    /**
     * Save the current user collection
     *
     * @param      UserCollection  $collection  A user collection to save
     *
     * @return     bool            True if the user collection has been saved else false
     *
     * @todo Can't update a User entity
     */
    public function saveUserCollection($collection): bool
    {
        $success = $this->userEntityManager->saveCollection($collection);

        foreach ($this->userEntityManager->getEntityCollection() as $user) {
            if ($success && $user->getRight() !== null) {
                $success = $this->userRightEntityManager->saveEntity($user->getRight());
            }

            if ($success && $user->getChatRight() !== null) {
                foreach ($user->getChatRight() as $chatRight) {
                    $success = $this->userChatRightEntityManager->saveEntity($chatRight);
                }
            }
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
