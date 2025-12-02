<?php

namespace Eclipxe\Enum\Exceptions;

use Throwable;

class ValueOverrideException extends GenericOverrideException
{
    public static function create($className, $value, $previous = null)
    {
        // StatusEnum cannot override value to x
        return new self(static::formatGenericMessage($className, 'value', $value), 0, $previous);
    }
}
