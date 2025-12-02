<?php

namespace PhpCfdi\Credentials\Internal;

use UnexpectedValueException;

/** @internal  */
class BaseConverterSequence
{
    /** @var string */
    private $sequence;

    /** @var int */
    private $length;

    public function __construct($sequence)
    {
        self::checkIsValid($sequence);

        $this->sequence = $sequence;
        $this->length = strlen($sequence);
    }

    public function __toString()
    {
        return $this->sequence;
    }

    public function value()
    {
        return $this->sequence;
    }

    public function length()
    {
        return $this->length;
    }

    public static function isValid($value)
    {
        try {
            static::checkIsValid($value);
            return true;
        } catch (UnexpectedValueException $exception) {
            return false;
        }
    }

    public static function checkIsValid($sequence)
    {
        $length = strlen($sequence);

        // is not empty
        if ($length < 2) {
            throw new UnexpectedValueException('Sequence does not contains enough elements');
        }

        if ($length !== mb_strlen($sequence)) {
            throw new UnexpectedValueException('Cannot use multibyte strings in dictionary');
        }

        $valuesCount = array_count_values(str_split(strtoupper($sequence)));
        $repeated = array_filter($valuesCount, function ($count) {
            return (1 !== $count);
        });
        if ([] !== $repeated) {
            throw new UnexpectedValueException(
                sprintf('The sequence has not unique values: "%s"', implode(', ', array_keys($repeated)))
            );
        }
    }
}
