<?php
/**
 * Trait to use utilities methods like a shortcut with many actions in one call
 *
 * @package    Trait
 * @author     Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace traits;

/**
 * Utilities methods to minimize code and process many actions in one call
 */
trait ShortcutsTrait
{
    /**
     * Perform an in_array research for each specified sub-array
     *
     * @param      mixed                $needle     The value to research
     * @param      array|\ArrayAccess   $haystack   The array to perform the research
     * @param      string               $key        The sub-array key
     *
     * @return     bool    True if the value is found else false
     */
    public static function inSubArray($needle, $haystack, string $key): bool
    {
        $found = false;

        foreach ($haystack as $subArray) {
            if ($subArray[$key] === $needle) {
                $found = true;
                break;
            }
        }

        return $found;
    }
}
