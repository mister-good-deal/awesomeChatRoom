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
        if (isset($_SESSION['user'])) {
            $userManager = new UserManager(unserialize($_SESSION['user']));

            if ($userManager->hasKibanaRight()) {
                echo file_get_contents('http://127.0.0.1:5601');
            } else {
                goto Unauthorized;
            }
        } else {
            Unauthorized:;
            header('HTTP/1.1 401 Unauthorized');
            // @todo check if Apache can serve custom page response on HTTP error code
            echo file_get_contents('../static/html/401Unauthorized.html');
        }
    }

    /**
     * Get the kibana iframe
     */
    public function getIframe()
    {
        echo json_encode([
            'src' => 'http://awesomechatroom.dev:8080/kibana/index',
        ]);
    }
}
