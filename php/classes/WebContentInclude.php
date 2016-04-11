<?php
/**
 * Utility class to include web "static" files in the main index.php for the single page pattern
 *
 * @package    Web
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace classes;

/**
 * Utility class to include web "static" files in the main index.php for the single page pattern
 */
class WebContentInclude
{
    /*=====================================
    =            Magic methods            =
    =====================================*/

    /**
     * Constructor empty
     */
    public function __construct()
    {
    }

    /*-----  End of Magic methods  ------*/

    /*======================================
    =            Public methods            =
    ======================================*/

    /**
     * Include all the files in the passed directory
     *
     * @param      string  $directoryPath  The directory path
     */
    public static function includeDirectoryFiles(string $directoryPath)
    {
        foreach ((new \DirectoryIterator($directoryPath)) as $fileInfo) {
            if (!$fileInfo->isDot() && $fileInfo->isFile()) {
                include $fileInfo->getPathname();
            }
        }
    }

    /*=====  End of Public methods  ======*/
}
