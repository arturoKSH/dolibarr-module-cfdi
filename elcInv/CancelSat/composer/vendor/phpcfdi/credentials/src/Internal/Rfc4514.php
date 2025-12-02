<?php

namespace PhpCfdi\Credentials\Internal;

/**
 * This class is created to replace escape strings and arrays according to RFC 4514
 * @see https://www.ietf.org/rfc/rfc4514.txt
 * @internal
 */
class Rfc4514
{
     const LEAD_CHARS = null; // [' ', '#'];

     const LEAD_REPLACEMENTS = null; // ['\20', '\22'];

     const TRAIL_CHARS = null; // [' '];

     const TRAIL_REPLACEMENTS = null; // ['\20'];

     const INNER_CHARS = null; // ['\\', '"', '+', ',', ';', '<', '=', '>'];

     const INNER_REPLACEMENTS = null; // ['\5C', '\22', '\2b', '\2c', '\3b', '\3c', '\3d', '\3e'];

    public function escape($subject)
    {
        $prefix = '';
        $sufix = '';
        $firstChar = substr($subject, 0, 1);
        //if (in_array($firstChar, self::LEAD_CHARS, true)) { ->Erick
        if (in_array($firstChar, [' ', '#'], true)) {
            //$prefix = str_replace(self::LEAD_CHARS, self::LEAD_REPLACEMENTS, $firstChar); ->Erick
            $prefix = str_replace([' ', '#'], ['\20'], $firstChar);
            $subject = substr($subject, 1);
        }

        $lastChar = substr($subject, -1);
        //if (in_array($lastChar, self::TRAIL_CHARS, true)) { ->Erick
        if (in_array($lastChar, [' ', '#'], true)) {
            //$sufix = str_replace(self::TRAIL_CHARS, self::TRAIL_REPLACEMENTS, $lastChar); ->Erick
            $sufix = str_replace([' ', '#'], ['\20'], $lastChar);
            $subject = substr($subject, 0, -1);
        }

        return $prefix . str_replace(self::INNER_CHARS, self::INNER_REPLACEMENTS, $subject) . $sufix;
    }

    /**
     * @param array<string, string> $values
     * @return string
     */
    public function escapeArray($values)
    {
        return implode(',', array_map(
            function ($name, $value) {
                return $this->escape($name) . '=' . $this->escape($value);
            },
            array_keys($values),
            $values
        ));
    }
}
