<?php


namespace Firesphere\SolrSearch\Traits;

use SilverStripe\Dev\Deprecation;

trait GetterSetterTrait
{
    /**
     * @param array $class
     * @return $this
     */
    public function setClasses($class): self
    {
        $this->class = $class;

        return $this;
    }

    /**
     * $options is not used anymore, added for backward compatibility
     * @param $class
     * @param array $options
     * @return $this
     */
    public function addClass($class, $options = array()): self
    {
        if (count($options)) {
            Deprecation::notice('5.0', 'Options are not used anymore');
        }
        $this->class[] = $class;

        return $this;
    }

    /**
     * @return array
     */
    public function getClasses(): array
    {
        return $this->class;
    }

    /**
     * Boosted fields are used at index time, not at query time
     *
     * @param array $boostedFields
     * @return $this
     */
    public function setBoostedFields($boostedFields): self
    {
        $this->boostedFields = $boostedFields;

        return $this;
    }

    /**
     * Add a boosted field to be boosted at query time
     *
     * @param string $field
     * @param array $extraOptions
     * @param string $boost
     * @return $this
     */
    public function addBoostedField($field, $extraOptions = [], $boost): self
    {
        $this->boostedFields[$field] = $boost;

        return $this;
    }

    /**
     * @return array
     */
    public function getBoostedFields(): array
    {
        return $this->boostedFields;
    }

}