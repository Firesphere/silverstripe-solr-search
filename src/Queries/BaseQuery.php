<?php


namespace Firesphere\SearchConfig\Queries;

/**
 * Class BaseQuery
 * @package Firesphere\SearchConfig\Queries
 */
class BaseQuery
{
    /**
     * @var array classes to be searched through
     */
    protected $classes = [];

    /**
     * @var string|array The actual query to be executed
     */
    protected $query;

    /**
     * @var array
     */
    protected $filter = [];

    /**
     * @var array
     */
    protected $exclude = [];
    /**
     * @var int
     */
    protected $start = 0;

    /**
     * @var int
     */
    protected $rows = 10;

    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @var array
     */
    protected $sort = [];

    /**
     * @var array
     */
    protected $facets = [];

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
     * @var int
     */
    protected $facetsMinCount = 0;

    /**
     * @var array
     */
    protected $terms = [];

    /**
     * @var array
     */
    protected $highlight = [];

    /**
     * @return int
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @param int $start
     * @return $this
     */
    public function setStart($start)
    {
        $this->start = $start;

        return $this;
    }

    /**
     * @return int
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @param int $rows
     * @return $this
     */
    public function setRows($rows)
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param array $fields
     * @return $this
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @param string $field fieldname
     * @param string $query search filter on field name
     * @return $this
     */
    public function addField($field, $query)
    {
        $this->fields[$field] = $query;

        return $this;
    }

    /**
     * @return array
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * @param array $sort
     * @return $this
     */
    public function setSort($sort)
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFacets()
    {
        return $this->facets;
    }

    /**
     * @param mixed $facets
     * @return $this
     */
    public function setFacets($facets)
    {
        $this->facets = $facets;

        return $this;
    }

    /**
     * @param $field
     * @return $this
     */
    public function addFacet($field)
    {
        $this->facets[] = str_replace('.', '_', $field);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFacetsMinCount()
    {
        return $this->facetsMinCount;
    }

    /**
     * @param mixed $facetsMinCount
     * @return $this
     */
    public function setFacetsMinCount($facetsMinCount)
    {
        $this->facetsMinCount = $facetsMinCount;

        return $this;
    }

    /**
     * @return array
     */
    public function getTerms()
    {
        return $this->terms;
    }

    /**
     * @param array $terms
     * @return $this
     */
    public function setTerms($terms)
    {
        $this->terms = $terms;

        return $this;
    }

    /**
     * @param string $term
     * @param null|string $fields
     * @param array $boost
     * @param bool $fuzzy
     * @return $this
     */
    public function addTerm($term, $fields = null, $boost = [], $fuzzy = false)
    {
        $this->terms[] = [
            'text'   => $term,
            'fields' => $fields ? (array)$fields : null,
            'boost'  => $boost,
            'fuzzy'  => $fuzzy
        ];

        return $this;
    }

    /**
     * @return array|string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param array|string $query
     * @return $this
     */
    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    public function addFilter($field, $value)
    {
        $this->filter[$field] = $value;

        return $this;
    }

    /**
     * @return array
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param array $filter
     * @return $this
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function addExclude($field, $value)
    {
        $this->exclude[$field] = $value;

        return $this;
    }

    /**
     * @return array
     */
    public function getExclude()
    {
        return $this->exclude;
    }

    /**
     * @param array $exclude
     * @return $this
     */
    public function setExclude($exclude)
    {
        $this->exclude = $exclude;

        return $this;
    }

    /**
     * Add a facet field. Format:
     *
     * @param string $class The ClassName that's supposed to be faceted
     * @param array $options
     * @return BaseQuery
     */
    public function addFacetField($class, $options)
    {
        $this->facetFields[$class] = $options;

        return $this;
    }

    /**
     * @return array
     */
    public function getFacetFields()
    {
        return $this->facetFields;
    }

    /**
     * @param array $facetFields
     * @return BaseQuery
     */
    public function setFacetFields($facetFields)
    {
        $this->facetFields = $facetFields;

        return $this;
    }

    public function addClass($class)
    {
        $this->classes[] = $class;

        return $this;
    }

    /**
     * @return array
     */
    public function getClasses()
    {
        return $this->classes;
    }

    /**
     * @param array $classes
     * @return BaseQuery
     */
    public function setClasses($classes)
    {
        $this->classes = $classes;

        return $this;
    }

    /**
     * @param $field
     * @return $this
     */
    public function addHighlight($field)
    {
        $this->highlight[] = $field;

        return $this;
    }

    /**
     * @return array
     */
    public function getHighlight()
    {
        return $this->highlight;
    }

    /**
     * @param array $highlight
     * @return BaseQuery
     */
    public function setHighlight($highlight)
    {
        $this->highlight = $highlight;

        return $this;
    }
}
