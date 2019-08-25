<?php


namespace Firesphere\SolrSearch\Indexes;

use Exception;
use Firesphere\SolrSearch\Factories\QueryComponentFactory;
use Firesphere\SolrSearch\Helpers\SolrLogger;
use Firesphere\SolrSearch\Helpers\Synonyms;
use Firesphere\SolrSearch\Interfaces\ConfigStore;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Results\SearchResult;
use Firesphere\SolrSearch\Services\SchemaService;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Traits\GetterSetterTrait;
use Firesphere\SolrSearch\Traits\BaseIndexTrait;
use GuzzleHttp\Exception\GuzzleException;
use LogicException;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;
use SilverStripe\ORM\ValidationException;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\ArrayData;
use Solarium\Core\Client\Adapter\Guzzle;
use Solarium\Core\Client\Client;
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
    use GetterSetterTrait;
    use BaseIndexTrait;

    /**
     * Field types that can be added
     * Used in init to call build methods from configuration yml
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
        'StoredFields'
    ];
    /**
     * @var \Solarium\Core\Query\Result\Result
     */
    protected $rawQuery;
    /**
     * @var SchemaService
     */
    protected $schemaService;
    /**
     * @var QueryComponentFactory
     */
    protected $queryFactory;
    /**
     * The query terms as an array
     * @var array
     */
    protected $queryTerms = [];
    /**
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

            // If the old init method is found, skip the config based init
            if (!count($this->getClasses())) {
                Deprecation::notice(
                    '5',
                    'No classes to add to index found, did you maybe call parent::init() too early?'
                );
            }

            return;
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
     */
    public function doSearch(BaseQuery $query)
    {
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

        return $searchResult;
    }

    /**
     * @param BaseQuery $query
     * @return Query
     */
    public function buildSolrQuery(BaseQuery $query): Query
    {
        $clientQuery = $this->client->createSelect();
        $factory = $this->buildFactory($query, $clientQuery);

        $clientQuery = $factory->buildQuery();
        $this->queryTerms = $factory->getQueryArray();

        $queryData = implode(' ', $this->queryTerms);
        $clientQuery->setQuery($queryData);

        return $clientQuery;
    }

    /**
     * @param BaseQuery $query
     * @param Query $clientQuery
     * @return QueryComponentFactory|mixed
     */
    protected function buildFactory(BaseQuery $query, Query $clientQuery)
    {
        $factory = $this->queryFactory;

        $helper = $clientQuery->getHelper();

        $factory->setQuery($query);
        $factory->setClientQuery($clientQuery);
        $factory->setHelper($helper);
        $factory->setIndex($this);

        return $factory;
    }

    /**
     * Check if the query should be retried with spellchecking
     * @param BaseQuery $query
     * @param Result $result
     * @param SearchResult $searchResult
     * @return bool
     */
    protected function doRetry(BaseQuery $query, Result $result, SearchResult $searchResult): bool
    {
        return !$this->retry &&
            $query->shouldFollowSpellcheck() &&
            $result->getNumFound() === 0 &&
            $searchResult->getCollatedSpellcheck();
    }

    /**
     * @param BaseQuery $query
     * @param SearchResult $searchResult
     * @return SearchResult|mixed|ArrayData
     */
    protected function spellcheckRetry(BaseQuery $query, SearchResult $searchResult)
    {
        $terms = $query->getTerms();
        $terms[0]['text'] = $searchResult->getCollatedSpellcheck();
        $query->setTerms($terms);
        $this->retry = true;

        return $this->doSearch($query);
    }

    /**
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
     * @param bool $defaults Include UK to US synonyms
     * @return string
     */
    public function getSynonyms($defaults = true): string
    {
        $synonyms = Synonyms::getSynonymsAsString($defaults);
        $siteConfigSynonyms = SiteConfig::current_site_config()->getField('SearchSynonyms');

        return sprintf('%s%s', $synonyms, $siteConfigSynonyms);
    }

    /**
     * @return array
     */
    public function getQueryTerms(): array
    {
        return $this->queryTerms;
    }

    /**
     * @return QueryComponentFactory
     */
    public function getQueryFactory(): QueryComponentFactory
    {
        return $this->queryFactory;
    }
}
