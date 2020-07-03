<?php
/**
 * Trait GetterSetterTrait|Firesphere\SolrSearch\Traits\GetterSetterTrait Getters and setters that are duplicate among
 * classes like {@link \Firesphere\SolrSearch\Indexes\BaseIndex} and {@link \Firesphere\SolrSearch\Queries\BaseQuery}
 *
 * @package Firesphere\SolrSearch\Traits
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Traits;

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
     * Object::class   => [
     *      'BaseClass' => Object::class,
     *      'Field' => 'Relation.ID',
     *      'Title' => 'Relation'
     * ],
     *
     * The facets will be applied as a single "AND" query.
     * e.g. SiteTree_ChannelID:1 with Object_Relation_ID:5 will not be found,
     * if the facet filter requires the SiteTree_ChannelID to be 1 AND Object_Relation_ID to be 3 or 6
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
     * Get classes
     *
     * @return array
     */
    public function getClasses(): array
    {
        return $this->class;
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
        $this->class[] = $class;

        return $this;
    }

    /**
     * Add a boosted field to be boosted at query time
     *
     * This method is out of place in a way, but it's a shared method
     * between Index and Query, thus needs to be here.
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
