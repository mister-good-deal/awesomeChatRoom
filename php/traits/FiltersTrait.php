<?php
/**
 * Trait to use usefull filter to parse an output
 *
 * @category Trait
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace traits;

/**
 * Utility methods to filter arrays or strings
 *
 * @trait FiltersTrait
 */
trait FiltersTrait
{
    /**
     * Filter a matches array returned from a preg_match_all with named keys
     *
     * @param  array  $matches   The matches array returned by the preg_match_all function
     * @param  string $keyName   The key name of the future array (?P<keyName>expression)
     * @param  string $valueName The value name of the future array (?P<valueName>expression)
     * @return array             The filterd array with $keyName = $keyValue
     */
    public function filterPregMatchAllWithFlags($matches, $keyName, $valueName)
    {
        $cleanArray = array();

        foreach ($matches as $key => $values) {
            if (is_string($key) && $key === $keyName) {
                foreach ($values as $num => $value) {
                    $cleanArray[trim($value)] = trim($matches[$valueName][$num]);
                }
            }
        }

        return $cleanArray;
    }

    /**
     * Filter a fetchAll PDO array to parse results in columns
     *
     * @param  array $data The fetchAll return
     * @return array       The filtered array by columns
     */
    public function filterFecthAllByColumn($data)
    {
        $columnsArray = array();

        foreach ($data as $row) {
            foreach ($row as $columnName => $columnValue) {
                if (!isset($columnsArray[$columnName])) {
                    $columnsArray[$columnName] = array();
                }

                $columnsArray[$columnName][] = $columnValue;
            }
        }

        return $columnsArray;
    }

    /**
     * Sanitize a user input by stripping unwanted blank characters
     *
     * @param  string $input The user input
     * @return string        The sanitized user input
     */
    public function sanitizeInput($input)
    {
        return trim($input);
    }

    /**
     * Get the user input, return null if undefined or the sanitized value
     *
     * @param  string $input The user input
     * @return string|null        The sanitized user input or null if the input was undefined
     */
    public function getInput($input)
    {
        if (!isset($input)) {
            $input = null;
        } else {
            $input = $this->sanitizeInput($input);
        }

        return $input;
    }

    /**
     * Convert a DateInterval object to sec
     *
     * @param  \DateInterval $dateInterval The DateInterval object
     * @return integer                     The converted number of seconds
     */
    public function dateIntervalToSec($dateInterval)
    {
        $sec = 0;

        $sec += $dateInterval->s;
        $sec += $dateInterval->i * 60;
        $sec += $dateInterval->h * 3600;
        $sec += $dateInterval->d * 86400;
        $sec += $dateInterval->m * 2592000;
        $sec += $dateInterval->y * 31104000;

        return $sec;
    }
}
