<?php


namespace Firesphere\SolrSearch\Indexes;

use Exception;
use Firesphere\SolrSearch\Helpers\Synonyms;
use Firesphere\SolrSearch\Interfaces\ConfigStore;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Results\SearchResult;
use Firesphere\SolrSearch\Services\SchemaService;
use Firesphere\SolrSearch\Services\SolrCoreService;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;
use SilverStripe\SiteConfig\SiteConfig;
use Solarium\Core\Client\Client;
use Solarium\Core\Query\Helper;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Result;

/**
 * Class BaseIndex
 * @package Firesphere\SolrSearch\Indexes
 */
abstract class BaseIndex
{
    use Extensible;
    /**
     * @var Client
     */
    protected $client;
    /**
     * @var array
     */
    protected $class = [];

    /**
     * @var array
     */
    protected $fulltextFields = [];

    /**
     * [
     *     'FieldName' => 2,
     * ]
     * @var array
     */
    protected $boostedFields = [];

    /**
     * @var array
     */
    protected $filterFields = [];

    /**
     * @var array
     */
    protected $sortFields = [];

    /**
     * @var array
     */
    protected $facetFields = [];

    /**
     * @var array
     */
    protected $copyFields = [
        'text' => [
            '*'
        ],
    ];

    /**
     * @var string
     */
    protected $defaultField = 'text';

    /**
     * @var SchemaService
     */
    protected $schemaService;

    /**
     * BaseIndex constructor.
     */
    public function __construct()
    {
        // Set up the client
        $config = Config::inst()->get(SolrCoreService::class, 'config');
        $config['endpoint'] = $this->getConfig($config['endpoint']);
        $this->client = new Client($config);

        // Set up the schema service, only used in the generation of the schema
        $schemaService = Injector::inst()->get(SchemaService::class);
        $schemaService->setIndex($this);
        $schemaService->setStore(Director::isDev());
        $this->schemaService = $schemaService;

        $this->init();
    }

    /**
     * Build a full config for all given endpoints
     * This is to add the current index to e.g. an index or select
     * @param array $endpoints
     * @return array
     */
    public function getConfig($endpoints)
    {
        foreach ($endpoints as $host => $endpoint) {
            $endpoints[$host]['core'] = $this->getIndexName();
        }

        return $endpoints;
    }

    /**
     * @return string
     */
    abstract public function getIndexName();

    /**
     * Stub for backward compatibility
     * Required to initialise the fields if not from config.
     * @return mixed
     * @todo work from config first
     */
    abstract public function init();

    /**
     * @param BaseQuery $query
     * @param Controller $controller
     * @return SearchResult|Result
     */
    public function doSearch($query, $controller)
    {
        $start = $controller->getRequest()->getVar('start') ?: 0;
        $this->extend('onBeforeSearch', $query);
        // Build the actual query parameters
        $clientQuery = $this->buildSolrQuery($query);
        $clientQuery->setStart($start);
        // Get 10 times the amount of rows, to prevent canView errors (TBD)
        $clientQuery->setRows($query->getRows() * 10);
        // Build class filtering
        $this->buildClassFilter($query, $clientQuery);
        // Add highlighting
        $clientQuery->getHighlighting()->setFields($query->getHighlight());
        // Setup the facets
        $this->buildFacets($query, $clientQuery);
        // Add filters
        $this->buildFilters($query, $clientQuery);
        $this->buildExcludes($query, $clientQuery);

        // Filter out the fields we want to see if they're set
        if (count($query->getFields())) {
            $clientQuery->setFields($query->getFields());
        }

        if (Director::isDev() && Controller::curr()->getRequest()->getVar('debugquery')) {
            $clientQuery->getDebug();
        }

        $result = $this->client->select($clientQuery);

        $result = new SearchResult($result, $query, $controller);

        $this->extend('updateSearchResults', $result);
        $this->extend('onAfterSearch', $result);

        return $result;
    }

    /**
     * @param BaseQuery $query
     * @return Query
     */
    protected function buildSolrQuery($query)
    {
        $clientQuery = $this->client->createSelect();
        $helper = $clientQuery->getHelper();

        $q = [];
        foreach ($query->getTerms() as $search) {
            $term = $search['text'];
            $term = $this->escapeSearch($term, $helper);
            $q[] = $term;
            // If boosting is set, add the fields to boost
            if ($search['boost']) {
                foreach ($search['fields'] as $boostField) {
                    $q[] = $boostField . ':' . $term . '^' . $search['boost'];
                }
            }
        }

        $term = implode(' ', $q);

        $clientQuery->setQuery($term);

        return $clientQuery;
    }

    /**
     * @param string $searchTerm
     * @param Helper $helper
     * @return array
     */
    protected function escapeSearch($searchTerm, $helper)
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
     * Add filtered queries based on class hierarchy
     * We only need the class itself, since the hierarchy will take care of the rest
     * @param BaseQuery $query
     * @param Query $clientQuery
     * @return Query
     */
    public function buildClassFilter($query, $clientQuery)
    {
        foreach ($query->getClasses() as $class) {
            $clientQuery->createFilterQuery('classes')
                ->setQuery('ClassHierarchy:' . str_replace('\\', '\\\\', $class));
        }

        return $clientQuery;
    }

    /**
     * @param BaseQuery $query
     * @param Query $clientQuery
     */
    protected function buildFacets($query, Query $clientQuery)
    {
        $facets = $clientQuery->getFacetSet();
        foreach ($query->getFacetFields() as $field => $config) {
            $facets->createFacetField($config['Title'])->setField($config['Field']);
        }
        $facets->setMinCount($query->getFacetsMinCount());
    }

    /**
     * @param BaseQuery $query
     * @param Query $clientQuery
     * @return Query
     */
    protected function buildFilters($query, Query $clientQuery)
    {
        $filters = $query->getFilter();
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $clientQuery->createFilterQuery($field . '_' . $key)
                        ->setQuery($field . ':' . $item);
                }
            } else {
                $clientQuery->createFilterQuery($field)
                    ->setQuery($field . ':' . $value);
            }
        }

        return $clientQuery;
    }

    /**
     * @param BaseQuery $query
     * @param Query $clientQuery
     * @return Query
     */
    protected function buildExcludes($query, Query $clientQuery)
    {
        $filters = $query->getExclude();
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $clientQuery->createFilterQuery($field . $key)
                        ->setQuery('-(' . $field . ':' . $item . ')');
                }
            } else {
                $clientQuery->createFilterQuery($field)
                    ->setQuery('-(' . $field . ':' . $value . ')');
            }
        }

        return $clientQuery;
    }

    /**
     * @return array
     */
    public function getBoostedFields()
    {
        return $this->boostedFields;
    }

    /**
     * @param array $boostedFields
     * @return $this
     */
    public function setBoostedFields($boostedFields)
    {
        $this->boostedFields = $boostedFields;

        return $this;
    }

    /**
     * Upload config for this index to the given store
     *
     * @param ConfigStore $store
     */
    public function uploadConfig($store)
    {
        // @todo use types/schema/elevate rendering
        // Upload the config files for this index
        // Create a default schema which we can manage later
        $schema = (string)$this->schemaService->generateSchema();
        $store->uploadString(
            $this->getIndexName(),
            'schema.xml',
            $schema
        );


        $synonyms = $this->getSynonyms();

        // Upload synonyms
        $store->uploadString(
            $this->getIndexName(),
            'synonyms.txt',
            $synonyms
        );

        // Upload additional files
        foreach (glob($this->schemaService->getExtrasPath() . '/*') as $file) {
            if (is_file($file)) {
                $store->uploadFile($this->getIndexName(), $file);
            }
        }
    }

    /**
     * Add synonyms. Public to be extendable
     * @return string
     */
    public function getSynonyms()
    {
        $engSynonyms = Synonyms::getSynonymsAsString();

        return $engSynonyms . SiteConfig::current_site_config()->SearchSynonyms;
    }

    /**
     * $options is not used anymore, added for backward compatibility
     * @param $class
     * @param array $options
     * @return $this
     */
    public function addClass($class, $options = array())
    {
        if (count($options)) {
            Deprecation::notice('5', 'Options are not used anymore');
        }
        $this->class[] = $class;

        return $this;
    }

    /**
     * Extra options is not used, it's here for backward compatibility
     *
     * Boosted fields are used at index time, not at query time
     * @param $field
     * @param array $extraOptions
     * @param int $boost
     * @return $this
     * @throws Exception
     */
    public function addBoostedField($field, $extraOptions = [], $boost = 2)
    {
        if (!in_array($field, $this->getFulltextFields(), true)) {
            $this->addFulltextField($field);
        }

        $this->boostedFields[$field] = $boost;

        return $this;
    }

    /**
     * @return array
     */
    public function getFulltextFields()
    {
        return $this->fulltextFields;
    }

    /**
     * @param array $fulltextFields
     * @return $this
     */
    public function setFulltextFields($fulltextFields)
    {
        $this->fulltextFields = $fulltextFields;

        return $this;
    }

    /**
     * @param string $fulltextField
     * @return $this
     */
    public function addFulltextField($fulltextField)
    {
        $this->fulltextFields[] = $fulltextField;

        return $this;
    }

    /**
     * @param $sortField
     * @return $this
     */
    public function addSortField($sortField)
    {
        $this->addFulltextField($sortField);

        $this->sortFields[] = $sortField;

        $this->setSortFields(array_unique($this->getSortFields()));

        return $this;
    }

    /**
     * @return array
     */
    public function getSortFields()
    {
        return $this->sortFields;
    }

    /**
     * @param array $sortFields
     * @return BaseIndex
     */
    public function setSortFields($sortFields)
    {
        $this->sortFields = $sortFields;

        return $this;
    }

    /**
     * @param $field
     * @param array $options
     * @return $this
     */
    public function addFacetField($field, $options)
    {
        $this->facetFields[$field] = $options;

        if (!in_array($field, $this->getFilterFields(), true)) {
            $this->addFilterField($field);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getFilterFields()
    {
        return $this->filterFields;
    }

    /**
     * @param array $filterFields
     * @return $this
     */
    public function setFilterFields($filterFields)
    {
        $this->filterFields = $filterFields;

        return $this;
    }

    /**
     * @param $filterField
     * @return $this
     */
    public function addFilterField($filterField)
    {
        $this->filterFields[] = $filterField;

        return $this;
    }

    /**
     * @param string $field Name of the copyfield
     * @param array $options Array of all fields that should be copied to this copyfield
     * @return $this
     */
    public function addCopyField($field, $options)
    {
        $this->copyFields[$field] = $options;

        if (!in_array($field, $this->getFulltextFields(), true)) {
            $this->addFulltextField($field);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param array $class
     * @return BaseIndex
     */
    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * @return array
     */
    public function getFacetFields()
    {
        return $this->facetFields;
    }

    /**
     * @param array $facetFields
     * @return BaseIndex
     */
    public function setFacetFields($facetFields)
    {
        $this->facetFields = $facetFields;

        return $this;
    }

    /**
     * @return array
     */
    public function getCopyFields()
    {
        return $this->copyFields;
    }

    /**
     * @param array $copyField
     * @return $this
     */
    public function setCopyFields($copyField)
    {
        $this->copyFields = $copyField;

        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultField()
    {
        return $this->defaultField;
    }

    /**
     * @param string $defaultField
     * @return BaseIndex
     */
    public function setDefaultField($defaultField)
    {
        $this->defaultField = $defaultField;

        return $this;
    }

    /**
     * @param Client $client
     * @return BaseIndex
     */
    public function setClient($client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }
}
