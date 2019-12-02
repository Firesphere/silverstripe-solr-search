<?php


namespace Firesphere\SolrSearch\Indexes;

use Exception;
use Firesphere\SolrSearch\Factories\QueryComponentFactory;
use Firesphere\SolrSearch\Helpers\SolrLogger;
use Firesphere\SolrSearch\Helpers\Synonyms;
use Firesphere\SolrSearch\Interfaces\ConfigStore;
use Firesphere\SolrSearch\Models\SearchSynonym;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Results\SearchResult;
use Firesphere\SolrSearch\Services\SchemaService;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\States\SiteState;
use Firesphere\SolrSearch\Traits\BaseIndexTrait;
use Firesphere\SolrSearch\Traits\GetterSetterTrait;
use GuzzleHttp\Exception\GuzzleException;
use LogicException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;
use SilverStripe\ORM\ValidationException;
use SilverStripe\View\ArrayData;
use Solarium\Core\Client\Adapter\Guzzle;
use Solarium\Core\Client\Client;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Result;

/**
 * Base for creating a new Solr core.
 *
 * Base index settings and methods. Should be extended with at least a name for the index.
 * This is an abstract class that can not be instantiated on it's own
 *
 * @package Firesphere\SolrSearch\Indexes
 */
abstract class BaseIndex
{
    use Extensible;
    use Configurable;
    use Injectable;
    use GetterSetterTrait;
    use BaseIndexTrait;
    /**
     * Session key for the query history
     */
    const SEARCH_HISTORY_KEY = 'query_history';

    /**
     * Field types that can be added
     * Used in init to call build methods from configuration yml
     *
     * @array
     */
    private static $fieldTypes = [
        'FulltextFields',
        'SortFields',
        'FilterFields',
        'BoostedFields',
        'CopyFields',
        'DefaultField',
        'FacetFields',
        'StoredFields',
    ];
    /**
     * The raw query result
     *
     * @var Result
     */
    protected $rawQuery;
    /**
     * {@link SchemaService}
     *
     * @var SchemaService
     */
    protected $schemaService;
    /**
     * {@link QueryComponentFactory}
     *
     * @var QueryComponentFactory
     */
    protected $queryFactory;
    /**
     * The query terms as an array
     *
     * @var array
     */
    protected $queryTerms = [];
    /**
     * Should a retry occur if nothing was found and there are suggestions to follow
     *
     * @var bool
     */
    private $retry = false;

    /**
     * BaseIndex constructor.
     */
    public function __construct()
    {
        // Set up the client
        $config = Config::inst()->get(SolrCoreService::class, 'config');
        $config['endpoint'] = $this->getConfig($config['endpoint']);
        $this->client = new Client($config);
        $this->client->setAdapter(new Guzzle());
        $session = Controller::curr()->getRequest()->getSession();
        $this->history = $session->get(self::SEARCH_HISTORY_KEY) ?: [];

        // Set up the schema service, only used in the generation of the schema
        $schemaService = Injector::inst()->get(SchemaService::class, false);
        $schemaService->setIndex($this);
        $schemaService->setStore(Director::isDev());
        $this->schemaService = $schemaService;
        $this->queryFactory = Injector::inst()->get(QueryComponentFactory::class, false);

        $this->extend('onBeforeInit');
        $this->init();
        $this->extend('onAfterInit');
    }

    /**
     * Build a full config for all given endpoints
     * This is to add the current index to e.g. an index or select
     *
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
     * Name of this index.
     *
     * @return string
     */
    abstract public function getIndexName();

    /**
     * Required to initialise the fields.
     * It's loaded in to the non-static properties for backward compatibility with FTS
     * Also, it's a tad easier to use this way, loading the other way around would be very
     * memory intensive, as updating the config for each item is not efficient
     */
    public function init()
    {
        if (!self::config()->get($this->getIndexName())) {
            Deprecation::notice('5', 'Please set an index name and use a config yml');

            return;
        }

        if (!empty($this->getClasses()) && !$this->usedAllFields) {
            Deprecation::notice('5', 'It is adviced to use a config YML for most cases');
        }


        $this->initFromConfig();
    }

    /**
     * Generate the config from yml if possible
     */
    protected function initFromConfig(): void
    {
        $config = self::config()->get($this->getIndexName());

        if (!array_key_exists('Classes', $config)) {
            throw new LogicException('No classes to index found!');
        }

        $this->setClasses($config['Classes']);

        // For backward compatibility, copy the config to the protected values
        // Saves doubling up further down the line
        foreach (self::$fieldTypes as $type) {
            if (array_key_exists($type, $config)) {
                $method = 'set' . $type;
                $this->$method($config[$type]);
            }
        }
    }

    /**
     * Default returns a SearchResult. It can return an ArrayData if FTS Compat is enabled
     *
     * @param BaseQuery $query
     * @return SearchResult|ArrayData|mixed
     * @throws GuzzleException
     * @throws ValidationException
     * @throws \ReflectionException
     */
    public function doSearch(BaseQuery $query)
    {
        SiteState::alterQuery($query);
        // Build the actual query parameters
        $clientQuery = $this->buildSolrQuery($query);

        $this->extend('onBeforeSearch', $query, $clientQuery);

        try {
            $result = $this->client->select($clientQuery);
        } catch (Exception $e) {
            $logger = new SolrLogger();
            $logger->saveSolrLog('Query');
        }

        $this->rawQuery = $result;

        // Handle the after search first. This gets a raw search result
        $this->extend('onAfterSearch', $result);
        $searchResult = new SearchResult($result, $query, $this);
        if ($this->doRetry($query, $result, $searchResult)) {
            return $this->spellcheckRetry($query, $searchResult);
        }

        // And then handle the search results, which is a useable object for SilverStripe
        $this->extend('updateSearchResults', $searchResult);

        Controller::curr()
            ->getRequest()
            ->getSession()
            ->set(self::SEARCH_HISTORY_KEY, $this->getHistory());

        return $searchResult;
    }

    /**
     * From the given BaseQuery, generate a Solarium ClientQuery object
     *
     * @param BaseQuery $query
     * @return Query
     */
    public function buildSolrQuery(BaseQuery $query): Query
    {
        $clientQuery = $this->client->createSelect();
        $factory = $this->buildFactory($query, $clientQuery);

        $clientQuery = $factory->buildQuery();
        $this->queryTerms = $factory->getQueryArray();

        $queryData = implode(' ', $this->getQueryTerms());
        $clientQuery->setQuery($queryData);

        return $clientQuery;
    }

    /**
     * Build a factory to use in the SolrQuery building. {@link static::buildSolrQuery()}
     *
     * @param BaseQuery $query
     * @param Query $clientQuery
     * @return QueryComponentFactory|mixed
     */
    protected function buildFactory(BaseQuery $query, Query $clientQuery)
    {
        $factory = $this->getQueryFactory();

        $helper = $clientQuery->getHelper();

        $factory->setQuery($query);
        $factory->setClientQuery($clientQuery);
        $factory->setHelper($helper);
        $factory->setIndex($this);

        return $factory;
    }

    /**
     * Check if the query should be retried with spellchecking
     * Conditions are:
     * It is not already a retry with spellchecking
     * Spellchecking is enabled
     * If spellchecking is enabled and nothing is found OR it should follow spellchecking none the less
     * There is a spellcheck output
     *
     * @param BaseQuery $query
     * @param Result $result
     * @param SearchResult $searchResult
     * @return bool
     */
    protected function doRetry(BaseQuery $query, Result $result, SearchResult $searchResult): bool
    {
        return !$this->retry &&
            $query->hasSpellcheck() &&
            ($query->shouldFollowSpellcheck() || $result->getNumFound() === 0) &&
            $searchResult->getCollatedSpellcheck();
    }

    /**
     * Retry the query with the first collated spellcheck found.
     *
     * @param BaseQuery $query
     * @param SearchResult $searchResult
     * @return SearchResult|mixed|ArrayData
     * @throws GuzzleException
     * @throws ValidationException
     * @throws \ReflectionException
     */
    protected function spellcheckRetry(BaseQuery $query, SearchResult $searchResult)
    {
        $terms = $query->getTerms();
        $spellChecked = $searchResult->getCollatedSpellcheck();
        // Remove the fuzzyness from the collated check
        $term = preg_replace('/~\d+/', '', $spellChecked);
        $terms[0]['text'] = $term;
        $query->setTerms($terms);
        $this->retry = true;

        return $this->doSearch($query);
    }

    /**
     * Get all fields that are required for indexing in a unique way
     *
     * @return array
     */
    public function getFieldsForIndexing(): array
    {
        $facets = [];
        foreach ($this->getFacetFields() as $field) {
            $facets[] = $field['Field'];
        }
        // Return values to make the key reset
        // Only return unique values
        // And make it all a single array
        $fields = array_values(
            array_unique(
                array_merge(
                    $this->getFulltextFields(),
                    $this->getSortFields(),
                    $facets,
                    $this->getFilterFields()
                )
            )
        );

        $this->extend('updateFieldsForIndexing', $fields);

        return $fields;
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

        $this->getSynonyms($store);

        // Upload additional files
        foreach (glob($this->schemaService->getExtrasPath() . '/*') as $file) {
            if (is_file($file)) {
                $store->uploadFile($this->getIndexName(), $file);
            }
        }
    }

    /**
     * Add synonyms. Public to be extendable
     *
     * @param ConfigStore $store Store to use to write synonyms
     * @param bool $defaults Include UK to US synonyms
     * @return string
     */
    public function getSynonyms($store = null, $defaults = true)
    {
        $synonyms = Synonyms::getSynonymsAsString($defaults);
        $syn = SearchSynonym::get();
        foreach ($syn as $synonym) {
            $synonyms .= $synonym->Keyword . ',' . $synonym->Synonym . PHP_EOL;
        }

        // Upload synonyms
        if ($store) {
            $store->uploadString(
                $this->getIndexName(),
                'synonyms.txt',
                $synonyms
            );
        }

        return $synonyms;
    }

    /**
     * Get the final, generated terms
     *
     * @return array
     */
    public function getQueryTerms(): array
    {
        return $this->queryTerms;
    }

    /**
     * Get the QueryComponentFactory. {@link QueryComponentFactory}
     *
     * @return QueryComponentFactory
     */
    public function getQueryFactory(): QueryComponentFactory
    {
        return $this->queryFactory;
    }
}
