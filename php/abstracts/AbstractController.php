<?php
/**
 * Abstract Controller class to harmonize standards controllers methods
 *
 * @package    Abstract
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */
namespace abstracts;

use \classes\IniManager as Ini;

/**
 * Abstract Controller class to harmonize standards controllers methods
 *
 * @abstract
 */
abstract class AbstractController
{
    /**
     * Output a JSON response from a data array passed in parameter
     *
     * @param      array  $data   The data to output
     */
    public function JsonResponse(array $data)
    {
        Ini::setIniFileName(Ini::INI_CONF_FILE);

        // If the print SQL debug mode is on clean the buffer before output
        if (Ini::getParam('Console', 'printSql')) {
            ob_end_clean();
        }

        echo json_encode($data, true);
    }
}
