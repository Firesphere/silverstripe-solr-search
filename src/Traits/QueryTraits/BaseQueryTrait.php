<?php
/**
 * Trait BaseQueryTrait|Firesphere\SolrSearch\Traits\BaseQueryTrait Trait to clean up the
 * {@link \Firesphere\SolrSearch\Queries\BaseQuery}
 *
 * @package Firesphere\SolrSearch\Traits
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Traits;

use Minimalcode\Search\Criteria;
use SilverStripe\Core\ClassInfo;

/**
 * Trait BaseQueryTrait Extraction from the BaseQuery class to keep things readable.
 *
 * This trait adds the support for adding the basic field/filter/term/facet options
 *
 * @package Firesphere\SolrSearch\Traits
 */
trait BaseQueryTrait
{
    /**
     * @var array Terms to search
     */
    protected $terms = [];

    /**
     * @var array Fields to filter
     */
    protected $filter = [];

    /**
     * @var array Fields to search
     */
    protected $fields = [];

    /**
     * Key => value pairs of facets to apply
     * [
     *     'FacetTitle' => [1, 2, 3]
     * ]
     *
     * @var array
     */
    protected $facetFilter = [];

    /**
     * @var array Fields to exclude
     */
    protected $exclude = [];

    /**
     * Each boosted query needs a separate addition!
     * e.g. $this->addTerm('test', ['MyField', 'MyOtherField'], 3)
     * followed by
     * $this->addTerm('otherTest', ['Title'], 5);
     *
     * If you want a generic boost on all terms, use addTerm only once, but boost on each field
     *
     * The fields parameter is used to boost on
     *
     * For generic boosting, use @addBoostedField($field, $boost), this will add the boost at Index time
     *
     * @param string $term Term to search for
     * @param array $fields fields to boost on
     * @param int $boost Boost value
     * @param bool|float $fuzzy True or a value to the maximum amount of iterations
     * @return $this
     */
    public function addTerm($term, $fields = [], $boost = 0, $fuzzy = null): self
    {
        $this->terms[] = [
            'text'   => $term,
            'fields' => $fields,
            'boost'  => $boost,
            'fuzzy'  => $fuzzy,
        ];

        return $this;
    }

    /**
     * Adds filters to filter on by value
     *
     * @param string $field
     * @param string|array $value
     * @return $this
     */
    public function addFilter($field, $value): self
    {
        $field = str_replace('.', '_', $field);
        $this->filter[$field] = $value;

        return $this;
    }

    /**
     * Add a field to be returned
     *
     * @param string $field fieldname
     * @return $this
     */
    public function addField($field): self
    {
        $field = str_replace('.', '_', $field);
        $this->fields[] = $field;

        return $this;
    }

    /**
     * Exclude fields from the search action
     *
     * @param string $field
     * @param string|array $value
     * @return $this
     */
    public function addExclude($field, $value): self
    {
        $field = str_replace('.', '_', $field);
        $this->exclude[$field] = $value;

        return $this;
    }

    /**
     * @param string $class
     * @throws \ReflectionException
     *
    public function excludeSubclasses($class)
    {
        $criteria = Criteria::where('ClassName')
            ->is($class)
            ->not()
            ->in(ClassInfo::subclassesFor($class));
        // This is WIP. Needs to work with the actual Query
    }
     /**/

    /**
     * Add faceting
     *
     * @param string $field
     * @param string|array $value
     * @return $this
     */
    public function addFacetFilter($field, $value): self
    {
        $this->facetFilter[$field][] = $value;

        return $this;
    }
}
