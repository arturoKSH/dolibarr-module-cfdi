<?php

namespace Eclipxe\Enum\Exceptions;

use Throwable;
use TypeError;

class EnumConstructTypeError extends TypeError implements EnumExceptionInterface
{
    public static function create($className, $previous = null)
    {
        return new self(
            sprintf('Argument passed to %s must be integer for index or string for value', $className),
            0,
            $previous
        );
    }
}
