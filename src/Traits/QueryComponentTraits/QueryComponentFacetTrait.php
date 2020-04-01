<?php
/**
 * Trait QueryComponentFacetTrait|Firesphere\SolrSearch\Traits\QueryComponentFacetTrait Trait to set Faceting on fields
 * for the {@link \Firesphere\SolrSearch\Factories\QueryComponentFactory}
 *
 * @package Firesphere\SolrSearch\Traits
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

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
     * Add AND facet filters based on the current request
     */
    protected function buildAndFacetQuery()
    {
        $filterFacets = $this->query->getFacetFilter();
        /** @var null|Criteria $criteria */
        $criteria = null;
        foreach ($this->index->getFacetFields() as $class => $config) {
            if (isset($filterFacets[$config['Title']])) {
                // For the API generator, this needs to be old style list();
                list($filter, $field) = $this->getFieldFacets($filterFacets, $config);
                $this->createFacetCriteria($criteria, $field, $filter);
            }
        }
        if ($criteria) {
            $this->clientQuery
                ->createFilterQuery('facets')
                ->setQuery($criteria->getQuery());
        }
    }

    /**
     * Combine all facets as AND facet filters for the results
     *
     * @param null|Criteria $criteria
     * @param string $field
     * @param array $filter
     */
    protected function createFacetCriteria(&$criteria, string $field, array $filter)
    {
        if (!$criteria) {
            $criteria = Criteria::where($field)->is(array_pop($filter));
        }
        foreach ($filter as $filterValue) {
            $criteria->andWhere($field)->is($filterValue);
        }
    }

    /**
     * Get the field and it's respected values to filter on to generate Criteria from
     *
     * @param array $filterFacets
     * @param array $config
     * @return array
     */
    protected function getFieldFacets(array $filterFacets, $config): array
    {
        $filter = $filterFacets[$config['Title']];
        $filter = is_array($filter) ? $filter : [$filter];
        // Fields are "short named" for convenience
        $shortClass = getShortFieldName($config['BaseClass']);
        $field = $shortClass . '_' . str_replace('.', '_', $config['Field']);

        return [$filter, $field];
    }
}
