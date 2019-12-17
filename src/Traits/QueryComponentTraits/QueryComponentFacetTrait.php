<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Minimalcode\Search\Criteria;
use Solarium\Component\Facet\Field;
use Solarium\QueryType\Select\Query\Query;

/**
 * Trait QueryComponentFacetTrait deals with the facets.
 *
 * Faceting for any given query or index.
 *
 * @package Firesphere\SolrSearch\Traits
 */
trait QueryComponentFacetTrait
{
    /**
     * @var BaseIndex Index to query
     */
    protected $index;
    /**
     * @var BaseQuery Query to use
     */
    protected $query;
    /**
     * @var Query Solarium query
     */
    protected $clientQuery;

    /**
     * Add facets from the index
     */
    protected function buildFacets(): void
    {
        $facets = $this->clientQuery->getFacetSet();
        // Facets should be set from the index configuration
        foreach ($this->index->getFacetFields() as $class => $config) {
            $shortClass = getShortFieldName($config['BaseClass']);
            $field = $shortClass . '_' . str_replace('.', '_', $config['Field']);
            /** @var Field $facet */
            $facet = $facets->createFacetField('facet-' . $config['Title']);
            $facet->setField($field);
        }
        // Count however, comes from the query
        $facets->setMinCount($this->query->getFacetsMinCount());
    }

    /**
     * Add facet filters based on the current request
     */
    protected function buildFacetQuery()
    {
        $filterFacets = $this->query->getFacetFilter();
        foreach ($this->index->getFacetFields() as $class => $config) {
            if (isset($filterFacets[$config['Title']])) {
                $filter = $filterFacets[$config['Title']];
                $filter = is_array($filter) ? $filter : [$filter];
                // Fields are "short named" for convenience
                $shortClass = getShortFieldName($config['BaseClass']);
                $field = $shortClass . '_' . str_replace('.', '_', $config['Field']);
                $criteria = Criteria::where($field)->in($filter);
                $this->clientQuery
                    ->createFilterQuery('facet-' . $config['Title'])
                    ->setQuery($criteria->getQuery());
            }
        }
    }
}
