<?php


namespace Firesphere\SearchConfig\Indexes;

use Firesphere\SearchConfig\Interfaces\ConfigStore;
use Firesphere\SearchConfig\Queries\BaseQuery;
use Firesphere\SearchConfig\Services\SchemaService;
use Firesphere\SearchConfig\Services\SolrCoreService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\View\ViewableData;
use Solarium\Core\Client\Client;

abstract class BaseIndex extends ViewableData
{
    /**
     * @var array
     */
    protected $fulltextFields = [];

    /**
     * @var SchemaService
     */
    protected $schemaService;

    public function __construct()
    {
        parent::__construct();
        $this->schemaService = Injector::inst()->get(SchemaService::class);
        $this->init();
    }

    /**
     * Stub for backward compatibility
     * Required to initialise the fields if not from config.
     * @return mixed
     * @todo work from config first
     */
    public function init()
    {
        // no-op
    }

    /**
     * @param BaseQuery $query
     */
    public function doSearch($query)
    {
        // @todo make filterQuerySelects instead of a single query
        $q = $this->buildSolrQuery($query);

        // Solarium
        $config = Config::inst()->get(SolrCoreService::class, 'config');
        $client = new Client($config);

        $solariumQuery = $client->createSelect([
            'query'  => trim(implode(' ', $q)),
            'start'  => $query->getStart(),
            'rows'   => $query->getRows(),
            'fields' => $query->getFields() ?: '*,score',
            'sort'   => $query->getSort() ?: ''
        ]);

        $result = $client->select($solariumQuery);

        Debug::dump($result);
    }

    /**
     * @param BaseQuery $query
     * @return array
     */
    protected function buildSolrQuery($query)
    {
        $q = [];
        $hlq = [];
        foreach ($query->getTerms() as $search) {
            $text = $search['text'];
            // Split the query on parts between double quotes and rest of the text, to keep quoted parts as one
            preg_match_all('/"[^"]*"|\S+/', $text, $parts);

            $fuzzy = $search['fuzzy'] ? '~' : '';

            // @todo simplify
            $fields = isset($search['fields']) ? $search['fields'] : [];
            if (isset($search['boost'])) {
                $fields = array_merge($fields, array_keys($search['boost']));
            }

            // @todo use Solarium filterQuery instead if there are multiple queries with different fields
            foreach ($parts[0] as $part) {
                if ($fields) {
                    $searchq = [];
                    foreach ($fields as $field) {
                        $boost = isset($search['boost'][$field]) ? '^' . $search['boost'][$field] : '';
                        // Escape namespace separators in class names
                        $field = str_replace('\\', '\\\\', $field);

                        $searchq[] = "{$field}:{$part}{$fuzzy}{$boost}";
                    }
                    $q[] = '+(' . implode(' OR ', $searchq) . ')';
                } else {
                    $q[] = ' ' . $part . $fuzzy;
                }
                $hlq[] = $part;
            }
        }

        return count($q) ? $q : ['*'];
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

        // Upload additional files
        foreach (glob($this->schemaService->getExtrasPath() . '/*') as $file) {
            if (is_file($file)) {
                $store->uploadFile($this->getIndexName(), $file);
            }
        }
    }

    /**
     * @return string
     */
    abstract public function getIndexName();

    /**
     * @param string $fulltextField
     * @return BaseIndex
     */
    public function addFulltextField($fulltextField)
    {
        $this->fulltextFields[] = str_replace('.', '_', $fulltextField);

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
     * @return BaseIndex
     */
    public function setFulltextFields($fulltextFields)
    {
        $this->fulltextFields = $fulltextFields;

        return $this;
    }
}
