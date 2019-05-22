<?php


namespace Firesphere\SearchConfig\Queries;

/**
 * Class BaseQuery
 * @package Firesphere\SearchConfig\Queries
 */
class BaseQuery
{
    /**
     * @var string|array The actual query to be executed
     */
    protected $query;

    /**
     * @var int
     */
    protected $start = 0;

    /**
     * @var int
     */
    protected $rows = 10;

    /**
     * @var string|null
     */
    protected $fields;

    /**
     * @var array
     */
    protected $sort = [];

    /**
     * @var array
     */
    protected $facets = [];

    /**
     * @var int
     */
    protected $facetsMinCount = 0;

    /**
     * @var array
     */
    protected $terms = [];

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
     * @return string|null
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param string|null $fields
     * @return $this
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

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
     * @param array|string $query
     * @return BaseQuery
     */
    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @return array|string
     */
    public function getQuery()
    {
        return $this->query;
    }
}
