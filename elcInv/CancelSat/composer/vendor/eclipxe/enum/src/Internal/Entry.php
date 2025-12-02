<?php

namespace Eclipxe\Enum\Internal;

/**
 * This is where name, value and index is stored
 *
 * This is an internal class, do not use it by your own. Changes on this class are not library breaking changes.
 * @internal
 */
class Entry
{
    /** @var string */
    private $value;

    /** @var int */
    private $index;

    public function __construct($value, $index)
    {
        $this->value = $value;
        $this->index = $index;
    }

    public function value()
    {
        return $this->value;
    }

    public function index()
    {
        return $this->index;
    }

    public function equals( $other)
    {
        return ($this->equalValue($other->value()) && $this->equalIndex($other->index()));
    }

    public function equalValue($value)
    {
        return (0 === strcmp($this->value, $value));
    }

    public function equalIndex($index)
    {
        return ($this->index === $index);
    }
}
