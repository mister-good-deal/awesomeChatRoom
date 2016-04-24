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

    /**
     * Get microtime as an int but casted in a string
     *
     * @return     string  The current micotime as string
     */
    public static function microtimeAsInt(): string
    {
        $microtime = microtime();
        $comps     = explode(' ', $microtime);

        return sprintf('%d%03d', $comps[1], $comps[0] * 1000);
    }

    /**
     * Get random microtime (from now to one year before) as an int but casted in a string
     *
     * @return     string  A random microtime as string
     */
    public static function microtimeRandomAsInt(): string
    {
        $microtime = microtime();
        $comps     = explode(' ', $microtime);

        return sprintf('%d%03d', $comps[1] - rand(0, 31536000), $comps[0] * 1000);
    }
}
