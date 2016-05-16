<?php
/**
 * Trait to use useful filter to parse an output
 *
 * @package    Trait
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace traits;

/**
 * Utility methods to filter arrays or strings
 */
trait FiltersTrait
{
    /**
     * Filter a matches array returned from a preg_match_all with named keys
     *
     * @param      array   $matches    The matches array returned by the preg_match_all function
     * @param      string  $keyName    The key name of the future array (?P<keyName>expression)
     * @param      string  $valueName  The value name of the future array (?P<valueName>expression)
     *
     * @return     array   The filtered array with $keyName = $keyValue
     */
    public static function filterPregMatchAllWithFlags(array $matches, string $keyName, string $valueName): array
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
     * @param      array  $data   The fetchAll return
     *
     * @return     array  The filtered array by columns
     */
    public static function filterFetchAllByColumn(array $data): array
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
     * Filter a result array from the PHP Elasticsearch client search call
     *
     * @param      array  $data   The PHP Elasticsearch client search result
     *
     * @return     array  The filtered array with only fields
     */
    public static function filterEsHitsByArray(array $data): array
    {
        $filteredArray = [];

        foreach ($data['hits']['hits'] as $hit) {
            foreach ($hit as $hitName => $fields) {
                if ($hitName === 'fields') {
                    $result = [];

                    foreach ($fields as $filedName => $field) {
                        if ($filedName !== 'text') {
                            if (count($field) === 1) {
                                $result[$filedName] = $field[0];
                            } else {
                                $result[$filedName] = $field;
                            }
                        }
                    }

                    $filteredArray[] = $result;
                }
            }
        }

        return $filteredArray;
    }

    /**
     * Convert a DateInterval object to sec
     *
     * @param      \DateInterval  $dateInterval  The DateInterval object
     *
     * @return     int            The converted number of seconds
     *
     * @todo move to DateTrait
     */
    public static function dateIntervalToSec(\DateInterval $dateInterval): int
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
