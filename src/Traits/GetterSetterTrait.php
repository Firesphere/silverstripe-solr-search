<?php


namespace Firesphere\SolrSearch\Traits;

use SilverStripe\Dev\Deprecation;

/**
 * Trait GetterSetterTrait for getting and setting data
 *
 * Getters and setters shared between the Index and Query
 *
 * @package Firesphere\SolrSearch\Traits
 */
trait GetterSetterTrait
{
    /**
     * @var array Classes to use
     */
    protected $class = [];

    /**
     * Sets boosting at _index_ time or _query_ time. Depending on the usage of this trait
     * [
     *     'FieldName' => 2,
     * ]
     *
     * @var array
     */
    protected $boostedFields = [];

    /**
     * Format:
     * SiteTree::class   => [
     *      'BaseClass' => SiteTree::class,
     *      'Field' => 'ChannelID',
     *      'Title' => 'Channel'
     * ],
     *
     * @var array
     */
    protected $facetFields = [];

    /**
     * Set the classes
     *
     * @param array $class
     * @return $this
     */
    public function setClasses($class): self
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Add a class to index or query
     * $options is not used anymore, added for backward compatibility
     *
     * @param $class
     * @param array $options unused
     * @return $this
     */
    public function addClass($class, $options = []): self
    {
        if (count($options)) {
            Deprecation::notice('5.0', 'Options are not used anymore');
        }
        $this->class[] = $class;

        return $this;
    }

    /**
     * Get classes
     *
     * @return array
     */
    public function getClasses(): array
    {
        return $this->class;
    }

    /**
     * Add a boosted field to be boosted at query time
     *
     * @param string $field
     * @param array|int $options
     * @param int|null $boost
     * @return $this
     */
    public function addBoostedField($field, $options = [], $boost = null)
    {
        if ($boost === null && is_int($options)) {
            $boost = $options;
        }

        $this->boostedFields[$field] = $boost;

        return $this;
    }

    /**
     * Get the boosted fields
     *
     * @return array
     */
    public function getBoostedFields(): array
    {
        return $this->boostedFields;
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
     * Get the facet fields
     *
     * @return array
     */
    public function getFacetFields(): array
    {
        return $this->facetFields;
    }

    /**
     * Set the facet fields
     *
     * @param array $facetFields
     * @return $this
     */
    public function setFacetFields($facetFields): self
    {
        $this->facetFields = $facetFields;

        return $this;
    }
}
