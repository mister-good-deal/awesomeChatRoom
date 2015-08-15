<?php
/**
 * Test script for entity/manager patterns
 *
 * @category Test
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

use \classes\entities\User as User;
use \classes\entities\UserStatistics as UserStatistics;
use \classes\entitiesManager\UserEntityManager as UserEntityManager;
use \classes\entitiesManager\UserStatisticsEntityManager as UserStatisticsEntityManager;
use \classes\entitiesCollection\UserCollection as Collection;
use \classes\DataBase as DB;
use \classes\ExceptionManager as Exception;

include_once '../autoloader.php';

try {
    $userEntityManager           = new UserEntityManager();
    $userStatisticsEntityManager = new UserStatisticsEntityManager();
    $user                        = new User();
    $userStatistics              = new UserStatistics();
    $collection                  = new Collection();

    for ($i = 1; $i < 11; $i++) {
        $user        = new User();
        $user->id    = $i;
        $user->name  = 'User_' . $i;
        $user->email = 'user_' . $i . '@hotmail.com';
        $collection->add($user);
    }

    // echo $collection . PHP_EOL;

    $userEntityManager->setEntityCollection($collection);

    if (!$userEntityManager->saveCollection()) {
        echo 'Insertion failed' . PHP_EOL;
    } else {
        echo 'Insertion succeeded' . PHP_EOL;
    }

    // $userEntityManager->setEntity($user);
    // $userStatisticsEntityManager->setEntity($userStatistics);
    
    // $userStatisticsEntityManager->dropEntityTable();
    // $userEntityManager->dropEntityTable();

    // DB::setPrintSQL(true);
    
    // $userEntityManager->createEntityTable();
    // $userStatisticsEntityManager->createEntityTable();
} catch (Exception $e) {
} finally {
    exit(0);
}
