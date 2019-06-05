<?php


namespace Firesphere\SolrSearch\Indexes;

use Exception;
use Firesphere\SolrSearch\Helpers\Synonyms;
use Firesphere\SolrSearch\Interfaces\ConfigStore;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Results\SearchResult;
use Firesphere\SolrSearch\Services\SchemaService;
use Firesphere\SolrSearch\Services\SolrCoreService;
use LogicException;
use Minimalcode\Search\Criteria;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Security\Security;
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
    use Configurable;

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
     * Sets boosting at _index_ time
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
        '_text' => [
            '*'
        ],
    ];

    /**
     * @var string
     */
    protected $defaultField = '_text';

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

        $this->extend('onBeforeInit');
        $this->init();
        $this->extend('onAfterInit');
    }

    /**
     * Build a full config for all given endpoints
     * This is to add the current index to e.g. an index or select
     * @param array $endpoints
     * @return array
     */
    public function getConfig($endpoints): array
    {
        foreach ($endpoints as $host => $endpoint) {
            $endpoints[$host]['core'] = $this->getIndexName();
        }

        return $endpoints;
    }

    /**
     * @return string
     */
    abstract public function getIndexName(): string;

    /**
     * Required to initialise the fields.
     * It's loaded in to the non-static properties for backward compatibility with FTS
     */
    public function init(): void
    {
        if (!self::config()->get($this->getIndexName())) {
            Deprecation::notice('10.0', 'The configuration should be set from YML now');

            return;
        }

        $config = self::config()->get($this->getIndexName());

        if (!array_key_exists('Classes', $config)) {
            throw new LogicException('No classes to index found!');
        }

        $this->setClass($config['Classes']);

        if (array_key_exists('FulltextFields', $config)) {
            $this->setFulltextFields($config['FulltextFields']);
        }
        if (array_key_exists('SortFields', $config)) {
            $this->setSortFields($config['SortFields']);
        }
        if (array_key_exists('FilterFields', $config)) {
            $this->setFilterFields($config['FilterFields']);
        }
        if (array_key_exists('BoostFields', $config)) {
            $this->setBoostedFields($config['BoostFields']);
        }
    }

    /**
     * @deprecated This is used as an Fulltext Search compatibility method. Call doSearch instead with the correct Query
     * @param BaseQuery $query
     * @param int $start deprecated in favour of $query, exists for backward compatibility with FTS
     * @param int $limit deprecated in favour of $query, exists for backward compatibility with FTS
     * @param array $params deprecated in favour of $query, exists for backward compatibility with FTS
     * @param bool $spellcheck deprecated in favour of #query, exists for backward compatibility with FTS
     * @return SearchResult|Result
     */
    public function search($query, $start = 0, $limit = 10, $params = [], $spellcheck = true)
    {
        $query->getStart() ?: $query->setStart($start);
        $query->getRows() ?: $query->setRows($limit);
        if ($query->isSpellcheck() !== $spellcheck) {
            $query->setSpellcheck($spellcheck);
        }
        if ($params['fq'] && !count($query->getFields())) {
            $query->setFields($params['fq']);
        }

        return $this->doSearch($query);
    }

    /**
     * @param BaseQuery $query
     * @return SearchResult|Result
     */
    public function doSearch(BaseQuery $query)
    {

        $this->extend('onBeforeSearch', $query);
        // Build the actual query parameters
        $clientQuery = $this->buildSolrQuery($query);
        // Build class filtering
        $this->buildClassFilter($query, $clientQuery);
        // Limit the results based on viewability
        $this->buildViewFilter($clientQuery);
        // Add filters
        $this->buildFilters($query, $clientQuery);
        // And excludes
        $this->buildExcludes($query, $clientQuery);
        // Add boosting
        $this->buildBoosts($query, $clientQuery);
        // Add highlighting
        $clientQuery->getHighlighting()->setFields($query->getHighlight());

        // Setup the facets
        $this->buildFacets($query, $clientQuery);

        // Set the start
        $clientQuery->setStart($query->getStart());
        $clientQuery->setRows($query->getRows());
        // Add spellchecking
        if ($query->isSpellcheck()) {
            $this->buildSpellcheck($query, $clientQuery);
        }
        // Filter out the fields we want to see if they're set
        if (count($query->getFields())) {
            $clientQuery->setFields($query->getFields());
        }

        $result = $this->client->select($clientQuery);

        // Handle the after search first. This gets a raw search result
        $this->extend('onAfterSearch', $result);
        $searchResult = new SearchResult($result, $query);

        // And then handle the search results, which is a useable object for SilverStripe
        $this->extend('updateSearchResults', $searchResult);

        return $searchResult;
    }

    /**
     * @param BaseQuery $query
     * @return Query
     */
    protected function buildSolrQuery(BaseQuery $query): Query
    {
        $clientQuery = $this->client->createSelect();
        $helper = $clientQuery->getHelper();

        $searchQuery = [];
        foreach ($query->getTerms() as $search) {
            $term = $search['text'];
            $term = $this->escapeSearch($term, $helper);
            // We can add the same term multiple times with different boosts
            // Not ideal, but it might happen, so let's add the term itself only once
            if (!in_array($term, $searchQuery, true)) {
                $searchQuery[] = $term;
            }
            // If boosting is set, add the fields to boost
            if ($search['boost'] > 1) {
                foreach ($search['fields'] as $boostField) {
                    $criteria = Criteria::where($boostField)
                        ->is($term)
                        ->boost($search['boost']);
                    $searchQuery[] = $criteria->getQuery();
                }
            }
        }

        $term = implode(' ', $searchQuery);

        $clientQuery->setQuery($term);

        return $clientQuery;
    }

    /**
     * @param string $searchTerm
     * @param Helper $helper
     * @return string
     */
    protected function escapeSearch($searchTerm, Helper $helper): string
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
    protected function buildClassFilter(BaseQuery $query, $clientQuery): Query
    {
        if (count($query->getClasses())) {
            foreach ($query->getClasses() as &$class) {
                $class = str_replace('\\', '\\\\', $class);
            }
            unset($class);
            $criteria = Criteria::where('ClassHierarchy')->in($query->getClasses());
            $clientQuery->createFilterQuery('classes')
                ->setQuery($criteria->getQuery());
        }

        return $clientQuery;
    }

    /**
     * @param Query $clientQuery
     */
    protected function buildViewFilter(Query $clientQuery): void
    {
        // Filter by what the user is allowed to see
        $viewIDs = ['1-null']; // null is always an option as that means publicly visible
        $currentUser = Security::getCurrentUser();
        if ($currentUser) {
            $viewIDs[] = '1-' . $currentUser->ID;
        }
        /** Add canView criteria. These are based on {@link DataObjectExtension::ViewStatus()} */
        $query = Criteria::where('ViewStatus')->in($viewIDs);

        $clientQuery->createFilterQuery('ViewStatus')
            ->setQuery($query->getQuery());
    }

    /**
     * @param BaseQuery $query
     * @param Query $clientQuery
     * @return Query
     */
    protected function buildFilters(BaseQuery $query, Query $clientQuery): Query
    {
        $filters = $query->getFilter();
        foreach ($filters as $field => $value) {
            $value = is_array($value) ?: [$value];
            $criteria = Criteria::where($field)->in($value);
            $clientQuery->createFilterQuery($field)
                ->setQuery($criteria->getQuery());
        }

        return $clientQuery;
    }

    /**
     * @param BaseQuery $query
     * @param Query $clientQuery
     * @return Query
     */
    protected function buildExcludes(BaseQuery $query, Query $clientQuery): Query
    {
        $filters = $query->getExclude();
        foreach ($filters as $field => $value) {
            $value = is_array($value) ?: [$value];
            $criteria = Criteria::where($field)
                ->is($value)
                ->not();
            $clientQuery->createFilterQuery($field)
                ->setQuery($criteria->getQuery());
        }

        return $clientQuery;
    }

    /**
     * Add the index-time boosting to the query
     * @param BaseQuery $query
     * @param Query $clientQuery
     */
    protected function buildBoosts(BaseQuery $query, Query $clientQuery): void
    {
        $boosts = $query->getBoostedFields();
        $q = $clientQuery->getQuery();
        foreach ($boosts as $field => $boost) {
            foreach ($query->getTerms() as $term) {
                $booster = Criteria::where($field)
                    ->is($term)
                    ->boost($boost);
                $q .= ' ' . $booster->getQuery();
            }
        }

        $clientQuery->setQuery($q);
    }

    /**
     * @param BaseQuery $query
     * @param Query $clientQuery
     */
    protected function buildSpellcheck(BaseQuery $query, Query $clientQuery): void
    {
        // Assuming the first term is the term entered
        $queryString = $query->getTerms()[0]['text'];
        // Arbitrarily limit to 5 if the config isn't set
        $count = static::config()->get('spellcheckCount') ?: 5;
        $spellcheck = $clientQuery->getSpellcheck();
        $spellcheck->setQuery($queryString);
        $spellcheck->setCount($count);
        $spellcheck->setBuild(true);
        $spellcheck->setCollate(true);
        $spellcheck->setExtendedResults(true);
        $spellcheck->setCollateExtendedResults(true);

    }

    /**
     * @param BaseQuery $query
     * @param Query $clientQuery
     */
    protected function buildFacets(BaseQuery $query, Query $clientQuery): void
    {
        $facets = $clientQuery->getFacetSet();
        foreach ($query->getFacetFields() as $field => $config) {
            $facets->createFacetField($config['Title'])->setField($config['Field']);
        }
        $facets->setMinCount($query->getFacetsMinCount());
    }

    /**
     * Upload config for this index to the given store
     *
     * @param ConfigStore $store
     */
    public function uploadConfig(ConfigStore $store): void
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
     * @param bool $eng Include UK to US synonyms
     * @return string
     */
    public function getSynonyms($eng = true): string
    {
        $engSynonyms = '';
        if ($eng) {
            $engSynonyms = Synonyms::getSynonymsAsString();
        }

        return $engSynonyms . SiteConfig::current_site_config()->getField('SearchSynonyms');
    }

    /**
     * @return array
     */
    public function getFieldsForIndexing(): array
    {
        return array_merge(
            $this->getFulltextFields(),
            $this->getSortFields(),
            $this->getFilterFields()
        );
    }

    /**
     * @return array
     */
    public function getFulltextFields(): array
    {
        return $this->fulltextFields;
    }

    /**
     * @param array $fulltextFields
     * @return $this
     */
    public function setFulltextFields($fulltextFields): self
    {
        $this->fulltextFields = $fulltextFields;

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
     * Generate a yml version of the init method indexes
     */
    public function initToYml(): void
    {
        if (function_exists('yaml_emit')) {
            $result = [
                BaseIndex::class => [
                    $this->getIndexName() =>
                        [
                            'Classes'        => $this->getClass(),
                            'FulltextFields' => $this->getFulltextFields(),
                            'SortFields'     => $this->getSortFields(),
                            'FilterFields'   => $this->getFilterFields(),
                            'BoostFields'    => $this->getBoostedFields(),
                        ]
                ]
            ];

            Debug::dump(yaml_emit($result));

            return;
        }

        throw new LogicException('yaml-emit PHP module missing');
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
    public function getBoostedFields()
    {
        return $this->boostedFields;
    }

    /**
     * Boosted fields are used at index time, not at query time
     *
     * @param array $boostedFields
     * @return $this
     */
    public function setBoostedFields($boostedFields)
    {
        $this->boostedFields = $boostedFields;

        return $this;
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
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
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
}
