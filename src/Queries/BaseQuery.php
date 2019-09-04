<?php


namespace Firesphere\SolrSearch\Queries;

use Firesphere\SolrSearch\Traits\BaseQueryTrait;
use Firesphere\SolrSearch\Traits\GetterSetterTrait;
use SilverStripe\Core\Injector\Injectable;

/**
 * Class BaseQuery is the base of every query executed.
 *
 * Build a query to execute agains Solr. Uses as simle as possible an interface.
 * @package Firesphere\SolrSearch\Queries
 */
class BaseQuery
{
    use GetterSetterTrait;
    use BaseQueryTrait;
    use Injectable;
    /**
     * Pagination start
     * @var int
     */
    protected $start = 0;
    /**
     * Total rows to display
     * @var int
     */
    protected $rows = 10;
    /**
     * Always get the ID. If you don't, you need to implement your own solution
     * @var array
     */
    protected $fields = [];
    /**
     * Sorting
     * @var array
     */
    protected $sort = [];
    /**
     * Enable spellchecking?
     * @var bool
     */
    protected $spellcheck = true;
    /**
     * Follow spellchecking if there are no results
     * @var bool
     */
    protected $followSpellcheck = false;
    /**
     * Minimum results a facet query has to have
     * @var int
     */
    protected $facetsMinCount = 0;
    /**
     * Search terms
     * @var array
     */
    protected $terms = [];
    /**
     * Highlighted items
     * @var array
     */
    protected $highlight = [];

    /**
     * Get the offset to start
     * @return int
     */
    public function getStart(): int
    {
        return $this->start;
    }

    /**
     * Set the offset to start
     * @param int $start
     * @return $this
     */
    public function setStart($start): self
    {
        $this->start = $start;

        return $this;
    }

    /**
     * Get the rows to return
     * @return int
     */
    public function getRows(): int
    {
        return $this->rows;
    }

    /**
     * Set the rows to return
     * @param int $rows
     * @return $this
     */
    public function setRows($rows): self
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * Get the fields to return
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Set fields to be returned
     * @param array $fields
     * @return $this
     */
    public function setFields($fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Get the sort fields
     * @return array
     */
    public function getSort(): array
    {
        return $this->sort;
    }

    /**
     * Set the sort fields
     * @param array $sort
     * @return $this
     */
    public function setSort($sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * Get the facet count minimum to use
     * @return int
     */
    public function getFacetsMinCount(): int
    {
        return $this->facetsMinCount;
    }

    /**
     * Set the minimum count of facets to be returned
     * @param mixed $facetsMinCount
     * @return $this
     */
    public function setFacetsMinCount($facetsMinCount): self
    {
        $this->facetsMinCount = $facetsMinCount;

        return $this;
    }

    /**
     * Get the search terms
     * @return array
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    /**
     * Set the search tearms
     * @param array $terms
     * @return $this
     */
    public function setTerms($terms): self
    {
        $this->terms = $terms;

        return $this;
    }

    /**
     * Get the filters
     * @return array
     */
    public function getFilter(): array
    {
        return $this->filter;
    }

    /**
     * Set the query filters
     * @param array $filter
     * @return $this
     */
    public function setFilter($filter): self
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * Get the excludes
     * @return array
     */
    public function getExclude(): array
    {
        return $this->exclude;
    }

    /**
     * Set the query excludes
     * @param array $exclude
     * @return $this
     */
    public function setExclude($exclude): self
    {
        $this->exclude = $exclude;

        return $this;
    }

    /**
     * Add a highlight parameter
     * @param $field
     * @return $this
     */
    public function addHighlight($field): self
    {
        $this->highlight[] = $field;

        return $this;
    }

    /**
     * Get the highlight parameters
     * @return array
     */
    public function getHighlight(): array
    {
        return $this->highlight;
    }

    /**
     * Set the highlight parameters
     * @param array $highlight
     * @return $this
     */
    public function setHighlight($highlight): self
    {
        $this->highlight = $highlight;

        return $this;
    }

    /**
     * Do we have spellchecking
     * @return bool
     */
    public function hasSpellcheck(): bool
    {
        return $this->spellcheck;
    }

    /**
     * Set the spellchecking on this query
     * @param bool $spellcheck
     * @return self
     */
    public function setSpellcheck(bool $spellcheck): self
    {
        $this->spellcheck = $spellcheck;

        return $this;
    }

    /**
     * Set if we should follow spellchecking
     * @param bool $followSpellcheck
     * @return BaseQuery
     */
    public function setFollowSpellcheck(bool $followSpellcheck): BaseQuery
    {
        $this->followSpellcheck = $followSpellcheck;

        return $this;
    }

    /**
     * Should spellcheck suggestions be followed
     * @return bool
     */
    public function shouldFollowSpellcheck(): bool
    {
        return $this->followSpellcheck;
    }

    /**
     * Get the facet filtering
     * @return array
     */
    public function getFacetFilter(): array
    {
        return $this->facetFilter;
    }

    /**
     * Set the facet filtering
     * @param array $facetFilter
     * @return BaseQuery
     */
    public function setFacetFilter(array $facetFilter): self
    {
        $this->facetFilter = $facetFilter;

        return $this;
    }
}
