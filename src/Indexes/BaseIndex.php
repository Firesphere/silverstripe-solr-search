<?php


namespace Firesphere\SolrSearch\Indexes;

use Firesphere\SolrSearch\Factories\QueryComponentFactory;
use Firesphere\SolrSearch\Helpers\Synonyms;
use Firesphere\SolrSearch\Interfaces\ConfigStore;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Results\SearchResult;
use Firesphere\SolrSearch\Services\SchemaService;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Traits\BaseIndexTrait;
use Firesphere\SolrSearch\Traits\GetterSetterTrait;
use LogicException;
use Minimalcode\Search\Criteria;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\ArrayData;
use Solarium\Core\Client\Adapter\Guzzle;
use Solarium\Core\Client\Client;
use Solarium\Core\Query\Helper;
use Solarium\QueryType\Select\Query\Query;

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

    private static $fieldTypes = [
        'FulltextFields',
        'SortFields',
        'FilterFields',
        'BoostedFields',
        'CopyFields',
        'DefaultField',
        'FacetFields',
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
     * @var QueryComponentFactory
     */
    protected $queryFactory;

    /**
     * The query terms as an arary
     * @var array
     */
    protected $queryTerms = [];

    /**
     * @var array
     */
    protected $boostTerms = [];

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
        $this->queryFactory = Injector::inst()->get(QueryComponentFactory::class);

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
            Deprecation::notice('5.0', 'Please set an index name');

            return;
        }

        // If the old init method is found, skip the config based init
        if (count($this->getClasses())) {
            return;
        }

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
     */
    public function doSearch(BaseQuery $query)
    {
        $this->extend('onBeforeSearch', $query);
        // Build the actual query parameters
        $clientQuery = $this->buildSolrQuery($query);

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

        $queryArray = $this->buildTerms($query, $helper);

        $this->queryFactory->setQueryArray($queryArray);
        $this->queryFactory->setQuery($query);
        $this->queryFactory->setClientQuery($clientQuery);
        $this->queryFactory->setHelper($helper);

        $clientQuery = $this->queryFactory->buildQuery();
        $queryArray = $this->queryFactory->getQueryArray();
        $this->queryTerms = $queryArray;

        $q = array_merge($this->queryTerms, $this->boostTerms);

        $q = implode(' ', $q);
        $clientQuery->setQuery($q);

        return $clientQuery;
    }

    /**
     * @param BaseQuery $query
     * @param Helper $helper
     * @return array
     */
    protected function buildTerms($query, Helper $helper): array
    {
        $terms = $query->getTerms();

        $termsArray = [];

        $boostTerms = $this->boostTerms;

        foreach ($terms as $search) {
            $term = $search['text'];
            $term = $this->escapeSearch($term, $helper);
            $postfix = ''; // When doing fuzzy search, postfix, otherwise, don't
            if ($search['fuzzy']) {
                $postfix = '~';
                if (is_numeric($search['fuzzy'])) {
                    $postfix .= $search['fuzzy'];
                }
            }
            // We can add the same term multiple times with different boosts
            // Not ideal, but it might happen, so let's add the term itself only once
            if (!in_array($term, $termsArray, true)) {
                $termsArray[] = $term . $postfix;
            }
            // If boosting is set, add the fields to boost
            if ($search['boost'] > 1) {
                $boost = $this->buildQueryBoost($search, $term, $boostTerms);
                $this->boostTerms = array_merge($boostTerms, $boost);
            }
        }

        return array_unique($termsArray);
    }

    /**
     * @param string $searchTerm
     * @param Helper $helper
     * @return string
     */
    public function escapeSearch($searchTerm, Helper $helper): string
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
     * Set boosting at Query time
     *
     * @param array $search
     * @param string $term
     * @param array $searchQuery
     * @return array
     */
    protected function buildQueryBoost($search, string $term, array $searchQuery): array
    {
        foreach ($search['fields'] as $boostField) {
            $boostField = str_replace('.', '_', $boostField);
            $criteria = Criteria::where($boostField)
                ->is($term)
                ->boost($search['boost']);
            $searchQuery[] = $criteria->getQuery();
        }

        return $searchQuery;
    }

    /**
     * @return array
     */
    public function getFieldsForIndexing(): array
    {
        return array_unique(
            array_merge(
                $this->getFulltextFields(),
                $this->getSortFields(),
                $this->getFilterFields()
            )
        );
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
    public function getQueryTerms(): array
    {
        return $this->queryTerms;
    }

    /**
     * @return array
     */
    public function getBoostTerms(): array
    {
        return $this->boostTerms;
    }
}
