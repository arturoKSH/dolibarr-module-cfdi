<?php

namespace Eclipxe\Enum\Exceptions;

use BadMethodCallException as PhpBadMethodCallException;
use Throwable;

class BadMethodCallException extends PhpBadMethodCallException implements EnumExceptionInterface
{
    public static function create($className, $methodName, $previous = null)
    {
        return new self(
            sprintf('Call to undefined method %s::%s', $className, $methodName),
            0,
            $previous
        );
    }
}
