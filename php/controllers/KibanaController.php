<?php
/**
 * Kibana controller
 *
 * @package    Controller
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace controllers;

use \abstracts\AbstractController as Controller;
use \classes\managers\UserManager as UserManager;

/**
 * Kibana controller
 */
class KibanaController extends Controller
{
    /**
     * Simply redirect kibana to its own localhost homepage
     */
    public function index()
    {
        $userManager = new UserManager(isset($_SESSION['user']) ? unserialize($_SESSION['user']) : null);

        // var_dump(unserialize($_SESSION['user'])->getRight());
        if ($userManager->hasKibanaRight()) {
            echo file_get_contents('http://127.0.0.1:5601');
        } else {
            header('HTTP/1.1 401 Unauthorized');
            // @todo check if Apache can serve custom page response on HTTP error code
            echo file_get_contents('../static/html/401Unauthorized.html');
        }
    }
}
