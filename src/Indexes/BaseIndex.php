<?php


namespace Firesphere\SearchConfig\Indexes;

use Firesphere\SearchConfig\Interfaces\ConfigStore;
use Firesphere\SearchConfig\Queries\BaseQuery;
use Firesphere\SearchConfig\Services\SchemaService;
use Firesphere\SearchConfig\Services\SolrCoreService;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\Deprecation;
use Solarium\Core\Client\Client;

abstract class BaseIndex
{
    /**
     * @var array
     */
    protected $class = [];

    /**
     * @var array
     */
    protected $fulltextFields = [];

    /**
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
     * @var string
     */
    protected $copyField = '_text';

    /**
     * @var SchemaService
     */
    protected $schemaService;

    public function __construct()
    {
        $this->schemaService = Injector::inst()->get(SchemaService::class);
        $this->schemaService->setIndex($this);
        $this->schemaService->setStore(Director::isDev());

        $this->init();
    }

    /**
     * Stub for backward compatibility
     * Required to initialise the fields if not from config.
     * @return mixed
     * @todo work from config first
     */
    abstract public function init();

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
     * @param string $fulltextField
     * @return $this
     */
    public function addFulltextField($fulltextField)
    {
        $this->fulltextFields[] = $fulltextField;

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
     * Extra options is not used, it's here for backward compatibility
     * @param $field
     * @param array $extraOptions
     * @param int $boost
     * @return $this
     */
    public function addBoostedField($field, $extraOptions = [], $boost = 2)
    {
        $fields = $this->getFulltextFields();

        if (!in_array($field, $fields, false)) {
            $fields[] = $field;
        }

        $this->setFulltextFields($fields);
        $boostedFields = $this->getBoostedFields();
        $boostedFields[$field] = $boost;
        $this->setBoostedFields($boostedFields);

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
     * @return $this
     */
    public function addFacetField($field)
    {
        $this->facetFields[] = $field;

        if (!in_array($field, $this->getFulltextFields(), false)) {
            $this->addFulltextField($field);
        }

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
     * @param array $filterFields
     * @return $this
     */
    public function setFilterFields($filterFields)
    {
        $this->filterFields = $filterFields;

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
    public function getSortFields()
    {
        return $this->sortFields;
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
    public function getClass()
    {
        return $this->class;
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
    public function getFacetFields()
    {
        return $this->facetFields;
    }

    /**
     * @param string $copyField
     * @return BaseIndex
     */
    public function setCopyField($copyField)
    {
        $this->copyField = $copyField;

        return $this;
    }

    /**
     * @return string
     */
    public function getCopyField()
    {
        return $this->copyField;
    }
}
