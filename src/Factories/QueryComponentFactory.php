<?php


namespace Firesphere\SolrSearch\Factories;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Traits\QueryComponentBoostTrait;
use Firesphere\SolrSearch\Traits\QueryComponentFacetTrait;
use Firesphere\SolrSearch\Traits\QueryComponentFilterTrait;
use Solarium\Core\Query\Helper;
use Solarium\QueryType\Select\Query\Query;

/**
 * Class QueryComponentFactory
 * @package Firesphere\SolrSearch\Factories
 */
class QueryComponentFactory
{
    use QueryComponentFilterTrait;
    use QueryComponentBoostTrait;
    use QueryComponentFacetTrait;

    protected static $builds = [
        'Terms',
        'ViewFilter',
        'ClassFilter',
        'Filters',
        'Excludes',
        'Facets',
        'FacetQuery',
        'Spellcheck'
    ];
    /**
     * @var BaseQuery
     */
    protected $query;
    /**
     * @var Helper
     */
    protected $helper;
    /**
     * @var array
     */
    protected $queryArray = [];
    /**
     * @var BaseIndex
     */
    protected $index;

    /**
     * Build the full query
     * @return Query
     */
    public function buildQuery()
    {
        foreach (static::$builds as $build) {
            $method = sprintf('build%s', $build);
            $this->$method();
        }
        // Set the start
        $this->clientQuery->setStart($this->query->getStart());
        // Double the rows in case something has been deleted, but not from Solr
        $this->clientQuery->setRows($this->query->getRows() * 2);
        // Add highlighting before adding boosting
        $this->clientQuery->getHighlighting()->setFields($this->query->getHighlight());
        // Add boosting
        $this->buildBoosts();

        // Filter out the fields we want to see if they're set
        $fields = $this->query->getFacetFields();
        if (count($fields)) {
            // We _ALWAYS_ need the ClassName for getting the DataObjects back
            $fields = array_unique(['ClassName'], $fields);
            $this->clientQuery->setFields($fields);
        }

        return $this->clientQuery;
    }

    /**
     * @return BaseQuery
     */
    public function getQuery(): BaseQuery
    {
        return $this->query;
    }

    /**
     * @param BaseQuery $query
     * @return QueryComponentFactory
     */
    public function setQuery(BaseQuery $query): self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @return array
     */
    public function getQueryArray(): array
    {
        return array_merge($this->queryArray, $this->boostTerms);
    }

    /**
     * @param array $queryArray
     * @return QueryComponentFactory
     */
    public function setQueryArray(array $queryArray): QueryComponentFactory
    {
        $this->queryArray = $queryArray;

        return $this;
    }

    /**
     * @return Query
     */
    public function getClientQuery(): Query
    {
        return $this->clientQuery;
    }

    /**
     * @param Query $clientQuery
     * @return QueryComponentFactory
     */
    public function setClientQuery(Query $clientQuery): QueryComponentFactory
    {
        $this->clientQuery = $clientQuery;

        return $this;
    }

    /**
     * @return Helper
     */
    public function getHelper(): Helper
    {
        return $this->helper;
    }

    /**
     * @param Helper $helper
     * @return QueryComponentFactory
     */
    public function setHelper(Helper $helper): QueryComponentFactory
    {
        $this->helper = $helper;

        return $this;
    }

    /**
     * @return BaseIndex
     */
    public function getIndex(): BaseIndex
    {
        return $this->index;
    }

    /**
     * @param BaseIndex $index
     * @return QueryComponentFactory
     */
    public function setIndex(BaseIndex $index): QueryComponentFactory
    {
        $this->index = $index;

        return $this;
    }

    /**
     * @return void
     */
    protected function buildTerms(): void
    {
        $terms = $this->query->getTerms();

        $boostTerms = $this->getBoostTerms();

        foreach ($terms as $search) {
            $term = $search['text'];
            $term = $this->escapeSearch($term, $this->helper);
            $postfix = $this->isFuzzy($search);
            // We can add the same term multiple times with different boosts
            // Not ideal, but it might happen, so let's add the term itself only once
            if (!in_array($term, $this->queryArray, true)) {
                $this->queryArray[] = $term . $postfix;
            }
            // If boosting is set, add the fields to boost
            if ($search['boost'] > 1) {
                $boostTerms = $this->buildQueryBoost($search, $term, $boostTerms);
            }
        }
        // Clean up the boost terms, remove doubles
        $this->setBoostTerms(array_values(array_unique($boostTerms)));
    }

    /**
     * @param string $searchTerm
     * @param Helper $helper
     * @return string
     */
    public function escapeSearch($searchTerm, Helper $helper): string
    {
        $term = [];
        // Escape special characters where needed. Except for quoted parts, those should be phrased
        preg_match_all('/"[^"]*"|\S+/', $searchTerm, $parts);
        foreach ($parts[0] as $part) {
            // As we split the parts, everything with two quotes is a phrase
            if (substr_count($part, '"') === 2) {
                $term[] = $helper->escapePhrase($part);
            } else {
                $term[] = $helper->escapeTerm($part);
            }
        }

        return implode(' ', $term);
    }

    /**
     * If the search is fuzzy, add fuzzyness
     *
     * @param $search
     * @return string
     */
    protected function isFuzzy($search): string
    {
        // When doing fuzzy search, postfix, otherwise, don't
        if ($search['fuzzy']) {
            return '~' . (is_numeric($search['fuzzy']) ? $search['fuzzy'] : '');
        }

        return '';
    }

    /**
     * Add spellcheck elements
     */
    protected function buildSpellcheck(): void
    {
        // Assuming the first term is the term entered
        $queryString = implode(' ', $this->queryArray);
        // Arbitrarily limit to 5 if the config isn't set
        $count = BaseIndex::config()->get('spellcheckCount') ?: 5;
        $spellcheck = $this->clientQuery->getSpellcheck();
        $spellcheck->setQuery($queryString);
        $spellcheck->setCount($count);
        $spellcheck->setBuild(true);
        $spellcheck->setCollate(true);
        $spellcheck->setExtendedResults(true);
        $spellcheck->setCollateExtendedResults('true');
    }
}
