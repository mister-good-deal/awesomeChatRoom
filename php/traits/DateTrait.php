<?php
/**
 * Trait to simplify date/time manipulations
 *
 * @package    Trait
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace traits;

/**
 * Utility methods to simplify date/time manipulations
 */
trait DateTrait
{
    /**
     * Get the timezone offset between the given timezone and the local timezone
     *
     * @param      string  $remoteTimezone  The given timezone
     *
     * @return     int     The offset between local and given timezone in seconds
     */
    public static function getTimezoneOffset(string $remoteTimezone): int
    {
        $originDateTimeZone = new \DateTimeZone(date_default_timezone_get());
        $remoteDateTimeZone = new \DateTimeZone($remoteTimezone);
        $originDateTime = new \DateTime('now', $originDateTimeZone);
        $remoteDateTime = new \DateTime('now', $remoteDateTimeZone);

        return $originDateTimeZone->getOffset($originDateTime) - $remoteDateTimeZone->getOffset($remoteDateTime);
    }
}
