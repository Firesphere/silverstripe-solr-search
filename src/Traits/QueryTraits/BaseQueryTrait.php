<?php


namespace Firesphere\SolrSearch\Traits;

trait BaseQueryTrait
{
    /**
     * @var array
     */
    protected $terms = [];

    /**
     * @var array
     */
    protected $filter = [];

    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @var array
     */
    protected $facetFilter = [];

    /**
     * @var array
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
        $field = str_replace('.', '_', $field);
        $this->filter[$field] = $value;

        return $this;
    }

    /**
     * Add a field to be returned
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
     * @param $field
     * @param $value
     * @return $this
     */
    public function addExclude($field, $value): self
    {
        $field = str_replace('.', '_', $field);
        $this->exclude[$field] = $value;

        return $this;
    }

    /**
     * @param string $field
     * @param string $value
     * @return $this
     */
    public function addFacetFilter($field, $value): self
    {
        $this->facetFilter[$field][] = $value;

        return $this;
    }
}
