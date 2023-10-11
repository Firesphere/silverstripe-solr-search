<?php
/**
 * class BaseQuery|Firesphere\SolrSearch\Queries\BaseQuery Base of a Solr Query
 *
 * @package Firesphere\Solr\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Queries;

use Firesphere\SearchBackend\Queries\BaseQuery;
use Firesphere\SolrSearch\Traits\BaseQueryTrait;
use Firesphere\SolrSearch\Traits\GetterSetterTrait;
use SilverStripe\Core\Injector\Injectable;

/**
 * Class BaseQuery is the base of every query executed.
 *
 * Build a query to execute agains Solr. Uses as simle as possible an interface.
 *
 * @package Firesphere\Solr\Search
 */
class SolrQuery extends BaseQuery
{
    use GetterSetterTrait;
    use BaseQueryTrait;
    use Injectable;

    /**
     * @var int Pagination start
     */
    protected $start = 0;
    /**
     * @var int Total rows to display
     */
    protected $rows = 10;
    /**
     * @var array Always get the ID. If you don't, you need to implement your own solution
     */
    protected $fields = [];
    /**
     * @var array Sorting settings
     */
    protected $sort = [];
    /**
     * @var bool Enable spellchecking?
     */
    protected $spellcheck = true;
    /**
     * @var bool Follow spellchecking if there are no results
     */
    protected $followSpellcheck = false;
    /**
     * @var int Minimum results a facet query has to have
     */
    protected $facetsMinCount = 1;
    /**
     * @var array Search terms
     */
    protected $terms = [];
    /**
     * @var array Highlighted items
     */
    protected $highlight = [];

    /**
     * Get the offset to start
     *
     * @return int
     */
    public function getStart(): int
    {
        return $this->start;
    }

    /**
     * Set the offset to start
     *
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
     *
     * @return int
     */
    public function getRows(): int
    {
        return $this->rows;
    }

    /**
     * Set the rows to return
     *
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
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Set fields to be returned
     *
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
     *
     * @return array
     */
    public function getSort(): array
    {
        return $this->sort;
    }

    /**
     * Set the sort fields
     *
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
     *
     * @return int
     */
    public function getFacetsMinCount(): int
    {
        return $this->facetsMinCount;
    }

    /**
     * Set the minimum count of facets to be returned
     *
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
     *
     * @return array
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    /**
     * Set the search tearms
     *
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
     *
     * @return array
     */
    public function getFilter(): array
    {
        return $this->filter;
    }

    /**
     * Set the query filters
     *
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
     *
     * @return array
     */
    public function getExclude(): array
    {
        return $this->exclude;
    }

    /**
     * Set the query excludes
     *
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
     *
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
     *
     * @return array
     */
    public function getHighlight(): array
    {
        return $this->highlight;
    }

    /**
     * Set the highlight parameters
     *
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
     *
     * @return bool
     */
    public function hasSpellcheck(): bool
    {
        return $this->spellcheck;
    }

    /**
     * Set the spellchecking on this query
     *
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
     *
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
     *
     * @return bool
     */
    public function shouldFollowSpellcheck(): bool
    {
        return $this->followSpellcheck;
    }

    /**
     * Stub for AND facets to be get
     *
     * @return array
     */
    public function getAndFacetFilter(): array
    {
        return $this->getFacetFilter();
    }

    /**
     * Get the AND facet filtering
     *
     * @return array
     */
    public function getFacetFilter(): array
    {
        return $this->andFacetFilter;
    }

    /**
     * Stub for AND facets to be set
     *
     * @param array $facetFilter
     * @return BaseQuery
     */
    public function setAndFacetFilter(array $facetFilter): self
    {
        return $this->setFacetFilter($facetFilter);
    }

    /**
     * Set the AND based facet filtering
     *
     * @param array $facetFilter
     * @return BaseQuery
     */
    public function setFacetFilter(array $facetFilter): self
    {
        $this->andFacetFilter = $facetFilter;

        return $this;
    }

    /**
     * Get the OR based facet filtering
     *
     * @return array
     */
    public function getOrFacetFilter(): array
    {
        return $this->orFacetFilter;
    }

    /**
     * Set the OR based facet filtering
     *
     * @param array $facetFilter
     * @return BaseQuery
     */
    public function setOrFacetFilter(array $facetFilter): self
    {
        $this->orFacetFilter = $facetFilter;

        return $this;
    }
}
