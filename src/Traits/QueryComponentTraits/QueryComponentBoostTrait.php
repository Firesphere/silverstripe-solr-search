<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Factories\QueryComponentFactory;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Minimalcode\Search\Criteria;

trait QueryComponentBoostTrait
{
    /**
     * @var BaseQuery
     */
    protected $query;
    /**
     * @var array
     */
    protected $boostTerms = [];
    /**
     * @var array
     */
    protected $queryArray = [];

    /**
     * @return array
     */
    public function getBoostTerms(): array
    {
        return $this->boostTerms;
    }

    /**
     * @param array $boostTerms
     * @return QueryComponentFactory
     */
    public function setBoostTerms(array $boostTerms): self
    {
        $this->boostTerms = $boostTerms;

        return $this;
    }

    /**
     * Add the index-time boosting to the query
     */
    protected function buildBoosts(): void
    {
        $boosts = $this->query->getBoostedFields();
        $queries = $this->getQueryArray();
        foreach ($boosts as $field => $boost) {
            foreach ($queries as $term) {
                $booster = Criteria::where($field)
                    ->is($term)
                    ->boost($boost);
                $this->queryArray[] = $booster->getQuery();
            }
        }
    }

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
