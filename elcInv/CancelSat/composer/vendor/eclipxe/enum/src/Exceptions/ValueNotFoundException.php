<?php

namespace Eclipxe\Enum\Exceptions;

use Throwable;

class ValueNotFoundException extends GenericNotFoundException
{
    public static function create($className, $value, $previous = null)
    {
        // StatusEnum value x was not found
        return new self(static::formatGenericMessage($className, 'value', $value), 0, $previous);
    }
}
