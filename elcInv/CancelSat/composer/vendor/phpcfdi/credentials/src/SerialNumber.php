<?php

namespace PhpCfdi\Credentials;

use PhpCfdi\Credentials\Internal\BaseConverter;
use UnexpectedValueException;

/**
 * This class is used to load hexadecimal or decimal data as a certificate serial number.
 * It have its own class because SOLID and is easy to test in this way.
 * It is not intented to use in general.
 */
class SerialNumber
{
    /** @var string Hexadecimal representation */
    private $hexadecimal;

    public function __construct($hexadecimal)
    {
        if ('' === $hexadecimal) {
            throw new UnexpectedValueException('The hexadecimal string is empty');
        }
        if (0 === strcasecmp('0x', substr($hexadecimal, 0, 2))) {
            $hexadecimal = substr($hexadecimal, 2);
        }
        if (! boolval(preg_match('/^[0-9a-f]*$/', $hexadecimal))) {
            throw new UnexpectedValueException('The hexadecimal string contains invalid characters');
        }
        $this->hexadecimal = $hexadecimal;
    }

    public static function createFromHexadecimal($hexadecimal)
    {
        return new self($hexadecimal);
    }

    public static function createFromDecimal($decString)
    {
        $hexadecimal = BaseConverter::createBase36()->convert($decString, 10, 16);
        return new self($hexadecimal);
    }

    public static function createFromBytes($input)
    {
        $hexadecimal = implode('', array_map(
            function ($value) {
                return dechex(ord($value));
            },
            str_split($input, 1)
        ));
        return new self($hexadecimal);
    }

    public function hexadecimal()
    {
        return $this->hexadecimal;
    }

    public function bytes()
    {
        return implode('', array_map(function ($value) {
            return chr(intval(hexdec($value)));
        }, str_split($this->hexadecimal, 2) ?: []));
    }

    public function decimal()
    {
        return BaseConverter::createBase36()->convert($this->hexadecimal(), 16, 10);
    }
}
