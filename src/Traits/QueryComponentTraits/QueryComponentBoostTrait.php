<?php
/**
 * Trait QueryComponentBoostTrait|Firesphere\SolrSearch\Traits\QueryComponentBoostTrait Trait to set boosting on fields
 * for the {@link \Firesphere\SolrSearch\Factories\QueryComponentFactory}
 *
 * @package Firesphere\Solr\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Factories\QueryComponentFactory;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Minimalcode\Search\Criteria;

/**
 * Trait QueryComponentBoostTrait adds support for boosting to a QueryComponent
 *
 * @package Firesphere\Solr\Search
 */
trait QueryComponentBoostTrait
{
    /**
     * BaseQuery that is going to be executed
     *
     * @var BaseQuery
     */
    protected $query;
    /**
     * Terms that are going to be boosted
     *
     * @var array
     */
    protected $boostTerms = [];
    /**
     * Query set that has been executed
     *
     * @var array
     */
    protected $queryArray = [];

    /**
     * Get the boosted terms
     *
     * @return array
     */
    public function getBoostTerms(): array
    {
        return $this->boostTerms;
    }

    /**
     * Set the boosted terms manually
     *
     * @param array $boostTerms
     * @return QueryComponentFactory
     */
    public function setBoostTerms(array $boostTerms): self
    {
        $this->boostTerms = $boostTerms;

        return $this;
    }

    /**
     * Build the boosted field setup through Criteria
     *
     * Add the index-time boosting to the query
     */
    protected function buildBoosts(): void
    {
        $boostedFields = $this->query->getBoostedFields();
        $queries = $this->getQueryArray();
        foreach ($boostedFields as $field => $boost) {
            $terms = [];
            foreach ($queries as $term) {
                $terms[] = $term;
            }
            if (count($terms)) {
                $booster = Criteria::where(str_replace('.', '_', $field))
                    ->in($terms)
                    ->boost($boost);
                $this->queryArray[] = $booster->getQuery();
            }
        }
    }

    /**
     * Any class using this needs getQueryArray
     *
     * @return mixed
     */
    abstract public function getQueryArray();

    /**
     * Set boosting at Query time
     *
     * @param array $search
     * @param string $term
     * @param array $boostTerms
     * @return array
     */
    protected function buildQueryBoost($search, string $term, array &$boostTerms): array
    {
        foreach ($search['fields'] as $boostField) {
            $boostField = str_replace('.', '_', $boostField);
            $criteria = Criteria::where($boostField)
                ->is($term)
                ->boost($search['boost']);
            $boostTerms[] = $criteria->getQuery();
        }

        return $boostTerms;
    }
}
