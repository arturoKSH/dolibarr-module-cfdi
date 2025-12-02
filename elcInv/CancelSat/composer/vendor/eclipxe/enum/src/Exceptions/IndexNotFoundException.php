<?php

namespace Eclipxe\Enum\Exceptions;

use Throwable;

class IndexNotFoundException extends GenericNotFoundException
{
    public static function create($className, $value, $previous = null)
    {
        // StatusEnum index x was not found
        return new self(static::formatGenericMessage($className, 'index', $value), 0, $previous);
    }
}
