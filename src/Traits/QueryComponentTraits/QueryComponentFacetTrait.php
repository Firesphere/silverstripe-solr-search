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
            $field = str_replace('.', '_', $config['Field']);
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
            if (array_key_exists($config['Title'], $filterFacets) &&
                $filter = array_filter($filterFacets[$config['Title']], 'strlen')
            ) {
                // @todo add unit tests for this bit. It's crucial but untested
                $filter = is_array($filter) ? $filter : [$filter];
                $field = str_replace('.', '_', $config['Field']);
                $criteria = Criteria::where($field)->in($filter);
                $this->clientQuery
                    ->createFilterQuery('facet-' . $config['Title'])
                    ->setQuery($criteria->getQuery());
            }
        }
    }
}
