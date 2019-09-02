<?php


namespace Firesphere\SolrSearch\Queries;

use Firesphere\SolrSearch\Traits\BaseQueryTrait;
use Firesphere\SolrSearch\Traits\GetterSetterTrait;
use SilverStripe\Core\Injector\Injectable;

/**
 * Class BaseQuery
 * @package Firesphere\SolrSearch\Queries
 */
class BaseQuery
{
    use GetterSetterTrait;
    use BaseQueryTrait;
    use Injectable;
    /**
     * Key-value pairs of fields and what to filter against
     *
     * @var array
     */
    protected $filter = [];
    /**
     * Same as {@link self::$filter} but reverses
     * @var array
     */
    protected $exclude = [];
    /**
     * Key => value pairs of facets to apply
     * [
     *     'FacetTitle' => [1, 2, 3]
     * ]
     * @var array
     */
    protected $facetFilter = [];
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
     * @return int
     */
    public function getStart(): int
    {
        return $this->start;
    }

    /**
     * @param int $start
     * @return $this
     */
    public function setStart($start): self
    {
        $this->start = $start;

        return $this;
    }

    /**
     * @return int
     */
    public function getRows(): int
    {
        return $this->rows;
    }

    /**
     * @param int $rows
     * @return $this
     */
    public function setRows($rows): self
    {
        $this->rows = $rows;

        return $this;
    }

    /**
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
     * @return array
     */
    public function getSort(): array
    {
        return $this->sort;
    }

    /**
     * @param array $sort
     * @return $this
     */
    public function setSort($sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * @return int
     */
    public function getFacetsMinCount(): int
    {
        return $this->facetsMinCount;
    }

    /**
     * @param mixed $facetsMinCount
     * @return $this
     */
    public function setFacetsMinCount($facetsMinCount): self
    {
        $this->facetsMinCount = $facetsMinCount;

        return $this;
    }

    /**
     * @return array
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    /**
     * @param array $terms
     * @return $this
     */
    public function setTerms($terms): self
    {
        $this->terms = $terms;

        return $this;
    }

    /**
     * @return array
     */
    public function getFilter(): array
    {
        return $this->filter;
    }

    /**
     * @param array $filter
     * @return $this
     */
    public function setFilter($filter): self
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * @return array
     */
    public function getExclude(): array
    {
        return $this->exclude;
    }

    /**
     * @param array $exclude
     * @return $this
     */
    public function setExclude($exclude): self
    {
        $this->exclude = $exclude;

        return $this;
    }

    /**
     * @param $field
     * @return $this
     */
    public function addHighlight($field): self
    {
        $this->highlight[] = $field;

        return $this;
    }

    /**
     * @return array
     */
    public function getHighlight(): array
    {
        return $this->highlight;
    }

    /**
     * @param array $highlight
     * @return $this
     */
    public function setHighlight($highlight): self
    {
        $this->highlight = $highlight;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasSpellcheck(): bool
    {
        return $this->spellcheck;
    }

    /**
     * @param bool $spellcheck
     * @return self
     */
    public function setSpellcheck(bool $spellcheck): self
    {
        $this->spellcheck = $spellcheck;

        return $this;
    }

    /**
     * @param bool $followSpellcheck
     * @return BaseQuery
     */
    public function setFollowSpellcheck(bool $followSpellcheck): BaseQuery
    {
        $this->followSpellcheck = $followSpellcheck;

        return $this;
    }

    /**
     * @return bool
     */
    public function shouldFollowSpellcheck(): bool
    {
        return $this->followSpellcheck;
    }

    /**
     * @return array
     */
    public function getFacetFilter(): array
    {
        return $this->facetFilter;
    }

    /**
     * @param array $facetFilter
     * @return BaseQuery
     */
    public function setFacetFilter(array $facetFilter): self
    {
        $this->facetFilter = $facetFilter;

        return $this;
    }
}
