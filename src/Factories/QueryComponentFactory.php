<?php


namespace Firesphere\SolrSearch\Factories;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Minimalcode\Search\Criteria;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\Debug;
use SilverStripe\Security\Security;
use Solarium\Core\Query\Helper;
use Solarium\QueryType\Select\Query\Query;

class QueryComponentFactory
{
    /**
     * @var BaseQuery
     */
    protected $query;

    /**
     * @var Query
     */
    protected $clientQuery;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var array
     */
    protected $queryArray;

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
        $this->buildViewFilter();
        // Build class filtering
        $this->buildClassFilter();
        // Add filters
        $this->buildFilters();
        // And excludes
        $this->buildExcludes();
        // Setup the facets
        $this->buildFacets();
        // Build the facet filters
        $this->buildFacetQuery();
        // Add spellchecking
        $this->buildSpellcheck();
        // Set the start
        $this->clientQuery->setStart($this->query->getStart());
        // Double the rows in case something has been deleted, but not from Solr
        $this->clientQuery->setRows($this->query->getRows() * 2);
        // Add highlighting before adding boosting
        $this->clientQuery->getHighlighting()->setFields($this->query->getHighlight());
        // Add boosting
        $this->buildBoosts();

        // Filter out the fields we want to see if they're set
        if (count($this->query->getFields())) {
            $this->clientQuery->setFields($this->query->getFields());
        }

        return $this->clientQuery;
    }


    protected function buildViewFilter(): void
    {
        // Filter by what the user is allowed to see
        $viewIDs = ['1-null']; // null is always an option as that means publicly visible
        $currentUser = Security::getCurrentUser();
        if ($currentUser && $currentUser->exists()) {
            $viewIDs[] = '1-' . $currentUser->ID;
        }
        /** Add canView criteria. These are based on {@link DataObjectExtension::ViewStatus()} */
        $query = Criteria::where('ViewStatus')->in($viewIDs);

        $this->clientQuery->createFilterQuery('ViewStatus')
            ->setQuery($query->getQuery());
    }

    /**
     * Add filtered queries based on class hierarchy
     * We only need the class itself, since the hierarchy will take care of the rest
     */
    protected function buildClassFilter(): void
    {
        if (count($this->query->getClasses())) {
            $classes = $this->query->getClasses();
            $criteria = Criteria::where('ClassHierarchy')->in($classes);
            $this->clientQuery->createFilterQuery('classes')
                ->setQuery($criteria->getQuery());
        }
    }

    protected function buildFilters(): void
    {
        $filters = $this->query->getFilter();
        foreach ($filters as $field => $value) {
            $value = is_array($value) ? $value : [$value];
            $criteria = Criteria::where($field)->in($value);
            $this->clientQuery->createFilterQuery('filter-' . $field)
                ->setQuery($criteria->getQuery());
        }
    }

    protected function buildExcludes(): void
    {
        $filters = $this->query->getExclude();
        foreach ($filters as $field => $value) {
            $value = is_array($value) ? $value : [$value];
            $criteria = Criteria::where($field)
                ->in($value)
                ->not();
            $this->clientQuery->createFilterQuery('exclude-' . $field)
                ->setQuery($criteria->getQuery());
        }
    }

    protected function buildFacets(): void
    {
        $facets = $this->clientQuery->getFacetSet();
        // Facets should be set from the index configuration
        foreach ($this->index->getFacetFields() as $field => $config) {
            $facets->createFacetField($config['Title'])->setField($config['Field']);
        }
        // Count however, comes from the query
        $facets->setMinCount($this->query->getFacetsMinCount());
    }

    protected function buildFacetQuery()
    {
        $filterFacets = [];
        if (Controller::curr()) {
            $filterFacets = Controller::curr()->getRequest()->getVars();
            $filterFacets = array_merge($filterFacets, Controller::curr()->getRequest()->postVars());
        }
        foreach ($this->index->getFacetFields() as $class => $config) {
            if (array_key_exists($config['Title'], $filterFacets)) {
                $filter = array_filter($filterFacets[$config['Title']], 'strlen');
                if (count($filter)) {
                    $criteria = Criteria::where($config['Field'])->in($filter);
                    $this->clientQuery->createFilterQuery('facet-' . $config['Title'])
                        ->setQuery($criteria->getQuery());
                }
            }
        }
    }

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
    public function setQuery(BaseQuery $query): QueryComponentFactory
    {
        $this->query = $query;

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
     * @return array
     */
    public function getQueryArray(): array
    {
        return $this->queryArray;
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
}
