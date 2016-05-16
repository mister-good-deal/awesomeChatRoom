<?php
/**
 * GeoIp controller
 *
 * @package    Controller
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace controllers;

use \abstracts\AbstractController as Controller;
use \classes\IniManager as Ini;
use \GeoIp2\Database\Reader as Reader;

/**
 * GeoIp controller
 */
class GeoIpController extends Controller
{
    /**
     * Get the user latitude and longitude as array ['lat' => latitude, 'lon' => longitude] or empty array on error
     * using MaxMind database
     */
    public function getLocation()
    {
        try {
            $record   = (new Reader(Ini::getParam('GeoIp', 'databasePath')))->city($_SERVER['REMOTE_ADDR']);
            $location = ['lat' => $record->location->latitude, 'lon' => $record->location->longitude];
        } catch (\Exception $e) {
            $location = [];
        } finally {
            $this->JsonResponse($location);
        }
    }
}
