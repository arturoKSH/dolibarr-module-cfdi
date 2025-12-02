<?php

namespace Eclipxe\Enum\Internal;

use ArrayObject;

/**
 * This is a collection of Entry elements
 *
 * This is an internal class, do not use it by your own. Changes on this class are not library breaking changes.
 * @internal
 */
class  Entries
{
    /** @var ArrayObject<string, Entry>|array<string, Entry> */
    private $entries;

    public function __construct()
    {
        $this->entries = new ArrayObject();
    }

    /**
     * Obtain the list of registered possible values as an array of indices and values
     *
     * @return array<int, string>
     */
    public function toIndexValueArray()
    {
        $mixed = [];
        foreach ($this->entries as $entry) {
            $mixed[$entry->index()] = $entry->value();
        }
        return $mixed;
    }

    public function hasName($name)
    {
        return isset($this->entries[$this->normalizeName($name)]);
    }

    public function put($name, $entry)
    {
        $this->entries[$this->normalizeName($name)] = $entry;
    }

    public function append($entries)
    {
        // access to private property since it has no sense to expose it to the outside
        /**
         * @var string $name
         * @var Entry $entry
         */
        foreach ($entries->entries as $name => $entry) {
            $this->entries[$name] = $entry;
        }
    }

    public function findEntryByName($name)
    {
        //return $this->entries[$this->normalizeName($name)] ?? null; ->Erick
        return $this->entries[$this->normalizeName($name)] ?: null;
    }

    public function findEntryByValue($value)
    {
        foreach ($this->entries as $entry) {
            if ($entry->equalValue($value)) {
                return $entry;
            }
        }
        return null;
    }

    public function findEntryByIndex($index)
    {
        foreach ($this->entries as $entry) {
            if ($entry->equalIndex($index)) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * @return array<int, int>
     */
    public function indices()
    {
        $indices = [];
        foreach ($this->entries as $entry) {
            $indices[] = $entry->index();
        }
        return $indices;
    }

    public function nextIndex()
    {
        $indices = $this->indices();
        if ([] === $indices) {
            return 0;
        }
        return max($indices) + 1;
    }

    protected function normalizeName($name)
    {
        return strtolower($name);
    }
}
