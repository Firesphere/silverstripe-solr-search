<?php
/**
 * class QueryComponentFactory|Firesphere\SolrSearch\Factories\QueryComponentFactory Build a Query component
 *
 * @package Firesphere\SolrSearch\Factories
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Factories;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Traits\QueryComponentBoostTrait;
use Firesphere\SolrSearch\Traits\QueryComponentFacetTrait;
use Firesphere\SolrSearch\Traits\QueryComponentFilterTrait;
use Solarium\Core\Query\Helper;
use Solarium\QueryType\Select\Query\Query;

/**
 * Class QueryComponentFactory
 *
 * Build a query component for each available build part
 *
 * @package Firesphere\SolrSearch\Factories
 */
class QueryComponentFactory
{
    use QueryComponentFilterTrait;
    use QueryComponentBoostTrait;
    use QueryComponentFacetTrait;

    /**
     * Default fields that should always be added
     *
     * @var array
     */
    const DEFAULT_FIELDS = [
        SolrCoreService::ID_FIELD,
        SolrCoreService::CLASS_ID_FIELD,
        SolrCoreService::CLASSNAME,
    ];

    /**
     * Build methods to run
     *
     * @var array
     */
    protected static $builds = [
        'Terms',
        'ViewFilter',
        'ClassFilter',
        'ExcludeSubclassFilter',
        'Filters',
        'Excludes',
        'Facets',
        'FacetQuery',
        'Spellcheck',
    ];
    /**
     * BaseQuery that needs to be executed
     *
     * @var BaseQuery
     */
    protected $query;
    /**
     * Helper to escape the query terms properly
     *
     * @var Helper
     */
    protected $helper;
    /**
     * Resulting querie parts as an array
     *
     * @var array
     */
    protected $queryArray = [];
    /**
     * Index to query
     *
     * @var BaseIndex
     */
    protected $index;

    /**
     * Build the full query
     *
     * @return Query
     */
    public function buildQuery(): Query
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
        $fields = $this->query->getFields();
        if (count($fields)) {
            // We _ALWAYS_ need the ClassName for getting the DataObjects back
            $fields = array_merge(static::DEFAULT_FIELDS, $fields);
            $this->clientQuery->setFields($fields);
        }

        return $this->clientQuery;
    }

    /**
     * Get the base query
     *
     * @return BaseQuery
     */
    public function getQuery(): BaseQuery
    {
        return $this->query;
    }

    /**
     * Set the base query
     *
     * @param BaseQuery $query
     * @return self
     */
    public function setQuery(BaseQuery $query): self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Get the array of terms used to query Solr
     *
     * @return array
     */
    public function getQueryArray(): array
    {
        return array_merge($this->queryArray, $this->boostTerms);
    }

    /**
     * Set the array of queries that are sent to Solr
     *
     * @param array $queryArray
     * @return self
     */
    public function setQueryArray(array $queryArray): self
    {
        $this->queryArray = $queryArray;

        return $this;
    }

    /**
     * Get the client Query components
     *
     * @return Query
     */
    public function getClientQuery(): Query
    {
        return $this->clientQuery;
    }

    /**
     * Set a custom Client Query object
     *
     * @param Query $clientQuery
     * @return self
     */
    public function setClientQuery(Query $clientQuery): self
    {
        $this->clientQuery = $clientQuery;

        return $this;
    }

    /**
     * Get the query helper
     *
     * @return Helper
     */
    public function getHelper(): Helper
    {
        return $this->helper;
    }

    /**
     * Set the Helper
     *
     * @param Helper $helper
     * @return self
     */
    public function setHelper(Helper $helper): self
    {
        $this->helper = $helper;

        return $this;
    }

    /**
     * Get the BaseIndex
     *
     * @return BaseIndex
     */
    public function getIndex(): BaseIndex
    {
        return $this->index;
    }

    /**
     * Set a BaseIndex
     *
     * @param BaseIndex $index
     * @return self
     */
    public function setIndex(BaseIndex $index): self
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Build the terms and boost terms
     *
     * @return void
     */
    protected function buildTerms(): void
    {
        $terms = $this->query->getTerms();

        $boostTerms = $this->getBoostTerms();

        foreach ($terms as $search) {
            $term = $search['text'];
            $term = $this->escapeSearch($term);
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
     * Escape the search query
     *
     * @param string $searchTerm
     * @return string
     */
    public function escapeSearch($searchTerm): string
    {
        $term = [];
        // Escape special characters where needed. Except for quoted parts, those should be phrased
        preg_match_all('/"[^"]*"|\S+/', $searchTerm, $parts);
        foreach ($parts[0] as $part) {
            // As we split the parts, everything with two quotes is a phrase
            // We need however, to strip out double quoting
            if (substr_count($part, '"') === 2) {
                // Strip all double quotes out for the phrase.
                // @todo make this less clunky
                // @todo add useful tests for this
                $part = str_replace('"', '', $part);
                $term[] = $this->helper->escapePhrase($part);
            } else {
                $term[] = $this->helper->escapeTerm($part);
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
        $spellcheck->setCollateExtendedResults(true);
    }
}
