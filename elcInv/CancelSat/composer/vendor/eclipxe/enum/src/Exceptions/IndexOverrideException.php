<?php

namespace Eclipxe\Enum\Exceptions;

use Throwable;

class IndexOverrideException extends GenericOverrideException
{
    public static function create($className, $value, $previous = null)
    {
        // StatusEnum cannot override index to x
        return new self(static::formatGenericMessage($className, 'index', $value), 0, $previous);
    }
}
