<?php


namespace Firesphere\SolrSearch\Traits;

use SilverStripe\Dev\Deprecation;

trait GetterSetterTrait
{
    /**
     * @var array
     */
    protected $class = [];

    /**
     * Sets boosting at _index_ time or _query_ time. Depending on the usage of this trait
     * [
     *     'FieldName' => 2,
     * ]
     * @var array
     */
    protected $boostedFields = [];

    /**
     * Format:
     * SiteTree::class   => [
     *      'Field' => 'SiteTree_ChannelID',
     *      'Title' => 'Channel'
     * ],
     * @var array
     */
    protected $facetFields = [];

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
     * @param array|int $extraOptions
     * @param int|null $boost
     * @return $this
     */
    public function addBoostedField($field, $extraOptions = [], $boost = null): self
    {
        if ($boost === null && is_int($extraOptions)) {
            $boost = $extraOptions;
        }

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

    /**
     * @return array
     */
    public function getFacetFields(): array
    {
        return $this->facetFields;
    }

    /**
     * @param array $facetFields
     * @return $this
     */
    public function setFacetFields($facetFields): self
    {
        $this->facetFields = $facetFields;

        return $this;
    }
}
