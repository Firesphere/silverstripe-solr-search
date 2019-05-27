<?php


namespace Firesphere\SolrSearch\Queries;

/**
 * Class BaseQuery
 * @package Firesphere\SolrSearch\Queries
 */
class BaseQuery
{
    /**
     * @todo add user search history through the Query
     * @var array
     */
    protected $history = [];

    /**
     * @var array classes to be searched through
     */
    protected $classes = [];

    /**
     * @var string|array The actual query to be executed
     */
    protected $query;

    /**
     * Key-value pairs of fields and what to filter against
     *
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
     * @param string $term Term to search for
     * @param array $fields fields to boost on
     * @param array|bool $boost Boost value
     * @param bool $fuzzy Unused
     * @return $this
     */
    public function addTerm($term, $fields = [], $boost = false, $fuzzy = false)
    {
        $this->terms[] = [
            'text'   => $term,
            'fields' => $fields,
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
     * @param array $options:
     * [
     *     'Field' => 'Name_of_Field',
     *     'Title' => 'TitleToUseForRetrieving'
     * ]
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
     * @return $this
     */
    public function setFacetFields($facetFields)
    {
        $this->facetFields = $facetFields;

        return $this;
    }

    /**
     * @param string $class
     * @return $this
     */
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
     * @return $this
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
     * @return $this
     */
    public function setHighlight($highlight)
    {
        $this->highlight = $highlight;

        return $this;
    }
}
