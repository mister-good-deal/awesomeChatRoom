<?php
/**
 * Test script for ImagesManager class
 *
 * @category Test
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

use \classes\ImagesManager as Images;
use \classes\ExceptionManager as Exception;

include_once '../autoloader.php';

try {
    $image = new Images(__DIR__ . '\test.jpeg');
    $image->setImageSavePath(__DIR__ . '/testResizes');
    $image->generateResizedImagesByWidth(Images::$WIDTHS_16_9);
    $image->setImageSavePath(__DIR__ . '/testCopyright');
    $image->copyrightImage('©Copyright', 40, 'Verdana', 'bottom-right');

} catch (Exception $e) {
} finally {
    exit(0);
}
