<?php
/**
 * Test script for Benchmark class
 *
 * @category Test
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

use \classes\Benchmark as Benchmark;
use \classes\ExceptionManager as Exception;

include_once '../autoloader.php';

$func1 = function ($array) {
    $sum = 0;

    foreach ($array as $value) {
        $sum += $value;
    }

    return $sum;
};

$func2 = function ($array) {
    $sum = 0;

    return array_sum($array);
};

$functions = array($func1);

try {
    $benchmark = new Benchmark(array(
        'level 1.1' => 'value 1',
        'level 1.2dzaazd' => 'value 2',
        'level 1.3' => 'value 3',
        'level 1.4' => array(
            'level 2.1' => 'value 1',
            'level 2.2' => 'value 2',
            'level 2.3azdazdaz ' => 'value 3',
            'level 2.4' => array(
                'level 3.1' => 'value 1',
                'level 3.2' => 'value 2',
                'level 3.3' => 'value 3',
                'level 3.4' => array(
                    'level 4.1' => 'value 1',
                    'level 4.2' => 'value 2',
                    'level 4.3 daz azd ' => 'value 3',
                    'level 4.4' => 'value 4'
                    )
                )
            )
        ));
} catch (Exception $e) {
} finally {
    exit(0);
}
