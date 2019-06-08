<?php


namespace Firesphere\SolrSearch\Queries;

use Firesphere\SolrSearch\Traits\GetterSetterTrait;

/**
 * Class BaseQuery
 * @package Firesphere\SolrSearch\Queries
 */
class BaseQuery
{
    use GetterSetterTrait;
    /**
     * @todo add user search history through the Query
     * @var array
     */
    protected $history = [];

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
     * Always get the ID. If you don't, you need to implement your own solution
     * @var array
     */
    protected $fields = [];

    /**
     * @var array
     */
    protected $sort = [];

    /**
     * Enable spellchecking?
     * @var bool
     */
    protected $spellcheck = true;

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
     * @param array $fields
     * @return $this
     */
    public function setFields($fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @param string $field fieldname
     * @param string $query search filter on field name
     * @return $this
     */
    public function addField($field, $query): self
    {
        $this->fields[$field] = $query;

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
     * @param int $boost Boost value
     * @param bool $fuzzy Unused
     * @return $this
     */
    public function addTerm($term, $fields = [], $boost = 0, $fuzzy = false): self
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
     * @param string $field
     * @param string $value
     * @return $this
     */
    public function addFilter($field, $value): self
    {
        $this->filter[$field] = $value;

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
     * @param $field
     * @param $value
     * @return $this
     */
    public function addExclude($field, $value): self
    {
        $this->exclude[$field] = $value;

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
     * Add a facet field. Format:
     *
     * @param string $class The ClassName that's supposed to be faceted
     * @param array $options :
     * [
     *     'Field' => 'Name_of_Field',
     *     'Title' => 'TitleToUseForRetrieving'
     * ]
     * @return self
     */
    public function addFacetField($class, $options): self
    {
        $this->facetFields[$class] = $options;

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
    public function isSpellcheck(): bool
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
}
