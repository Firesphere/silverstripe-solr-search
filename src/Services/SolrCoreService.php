<?php

namespace Firesphere\SolrSearch\Services;

use Exception;
use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Helpers\FieldResolver;
use Firesphere\SolrSearch\Helpers\SolrLogger;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Interfaces\ConfigStore;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use LogicException;
use ReflectionClass;
use ReflectionException;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\SS_List;
use Solarium\Client;
use Solarium\Core\Client\Adapter\Guzzle;
use Solarium\QueryType\Server\CoreAdmin\Query\Query;
use Solarium\QueryType\Server\CoreAdmin\Result\StatusResult;
use Solarium\QueryType\Update\Result;

/**
 * Class SolrCoreService provides the base connection to Solr.
 *
 * Default service to connect to Solr and handle all base requirements to support Solr.
 * Default constants are available to support any set up.
 *
 * @package Firesphere\SolrSearch\Services
 */
class SolrCoreService
{
    use Injectable;
    /**
     * Unique ID in Solr
     */
    const ID_FIELD = 'id';
    /**
     * SilverStripe ID of the object
     */
    const CLASS_ID_FIELD = 'ObjectID';
    /**
     * Name of the field that can be used for queries
     */
    const CLASSNAME = 'ClassName';
    /**
     * Solr update types
     */
    const DELETE_TYPE_ALL = 'deleteall';
    /**
     * string
     */
    const DELETE_TYPE = 'delete';
    /**
     * string
     */
    const UPDATE_TYPE = 'update';
    /**
     * string
     */
    const CREATE_TYPE = 'create';


    use Configurable;

    /**
     * @var Client The current client
     */
    protected $client;
    /**
     * @var array Base indexes that exist
     */
    protected $baseIndexes = [];
    /**
     * @var array Valid indexes out of the base indexes
     */
    protected $validIndexes = [];
    /**
     * @var Query A core admin object
     */
    protected $admin;

    /**
     * Add debugging information
     *
     * @var bool
     */
    protected $inDebugMode = false;


    /**
     * SolrCoreService constructor.
     *
     * @throws ReflectionException
     */
    public function __construct()
    {
        $config = static::config()->get('config');
        $this->client = new Client($config);
        $this->client->setAdapter(new Guzzle());
        $this->admin = $this->client->createCoreAdmin();
        $this->baseIndexes = ClassInfo::subclassesFor(BaseIndex::class);
        $this->filterIndexes();
    }

    /**
     * Filter enabled indexes down to valid indexes that can be instantiated
     * or are allowed from config
     *
     * @throws ReflectionException
     */
    protected function filterIndexes(): void
    {
        $enabledIndexes = static::config()->get('indexes');
        $enabledIndexes = is_array($enabledIndexes) ? $enabledIndexes : $this->baseIndexes;
        foreach ($this->baseIndexes as $subindex) {
            // If the config of indexes is set, and the requested index isn't in it, skip addition
            // Or, the index simply doesn't exist, also a valid option
            if (!in_array($subindex, $enabledIndexes, true) ||
                !$this->checkReflection($subindex)
            ) {
                continue;
            }
            $this->validIndexes[] = $subindex;
        }
    }

    /**
     * Check if the class is instantiable
     *
     * @param $subindex
     * @return bool
     * @throws ReflectionException
     */
    protected function checkReflection($subindex): bool
    {
        $reflectionClass = new ReflectionClass($subindex);

        return $reflectionClass->isInstantiable();
    }

    /**
     * Create a new core
     *
     * @param $core string - The name of the core
     * @param ConfigStore $configStore
     * @return bool
     * @throws Exception
     * @throws GuzzleException
     */
    public function coreCreate($core, $configStore): bool
    {
        $action = $this->admin->createCreate();

        $action->setCore($core);
        $action->setInstanceDir($configStore->instanceDir($core));
        $this->admin->setAction($action);
        try {
            $response = $this->client->coreAdmin($this->admin);

            return $response->getWasSuccessful();
        } catch (Exception $e) {
            $solrLogger = new SolrLogger();
            $solrLogger->saveSolrLog('Config');

            throw new Exception($e);
        }
    }

    /**
     * Reload the given core
     *
     * @param $core
     * @return StatusResult|null
     */
    public function coreReload($core)
    {
        $reload = $this->admin->createReload();
        $reload->setCore($core);

        $this->admin->setAction($reload);

        $response = $this->client->coreAdmin($this->admin);

        return $response->getStatusResult();
    }

    /**
     * Check the status of a core
     *
     * @deprecated backward compatibility stub
     * @param string $core
     * @return StatusResult|null
     */
    public function coreIsActive($core)
    {
        return $this->coreStatus($core);
    }

    /**
     * Get the core status
     *
     * @param string $core
     * @return StatusResult|null
     */
    public function coreStatus($core)
    {
        $status = $this->admin->createStatus();
        $status->setCore($core);

        $this->admin->setAction($status);
        $response = $this->client->coreAdmin($this->admin);

        return $response->getStatusResult();
    }

    /**
     * Remove a core from Solr
     *
     * @param string $core core name
     * @return StatusResult|null A result is successful
     */
    public function coreUnload($core)
    {
        $unload = $this->admin->createUnload();
        $unload->setCore($core);

        $this->admin->setAction($unload);
        $response = $this->client->coreAdmin($this->admin);

        return $response->getStatusResult();
    }

    /**
     * Update items in the list to Solr
     *
     * @param SS_List|DataObject $items
     * @param string $type
     * @param null|string $index
     * @return bool|Result
     * @throws ReflectionException
     * @throws Exception
     */
    public function updateItems($items, $type, $index = null)
    {
        $indexes = $this->getValidIndexes($index);

        $result = false;
        $items = ($items instanceof DataObject) ? ArrayList::create([$items]) : $items;
        $items = ($items instanceof SS_List) ? $items : ArrayList::create($items);

        $hierarchy = FieldResolver::getHierarchy($items->first()->ClassName);

        foreach ($indexes as $indexString) {
            /** @var BaseIndex $index */
            $index = Injector::inst()->get($indexString);
            $classes = $index->getClasses();
            $inArray = array_intersect($classes, $hierarchy);
            // No point in sending a delete|update|create for something that's not in the index
            if (!count($inArray)) {
                continue;
            }

            $result = $this->doManipulate($items, $type, $index);
        }

        return $result;
    }

    /**
     * Get valid indexes for the project
     *
     * @param null|string $index
     * @return array
     */
    public function getValidIndexes($index = null): ?array
    {
        if ($index && !in_array($index, $this->validIndexes, true)) {
            throw new LogicException('Incorrect index ' . $index);
        }

        if ($index) {
            return [$index];
        }

        // return the array values, to reset the keys
        return array_values($this->validIndexes);
    }

    /**
     * Get all classes from all indexes and return them.
     * Used to get all classes that are to be indexed
     * @return array
     */
    public function getValidClasses()
    {
        $indexes = $this->getValidIndexes();
        $classes = [];
        foreach ($indexes as $index) {
            $indexClasses = singleton($index)->getClasses();
            $classes = array_merge($classes, $indexClasses);
        }

        return array_unique($classes);
    }

    /**
     * Is the given class a valid class to index
     *
     * @param string $class
     * @return bool
     */
    public function isValidClass($class): bool
    {
        $classes = $this->getValidClasses();

        return in_array($class, $classes, true);
    }

    /**
     * Execute the manipulation of solr documents
     *
     * @param SS_List $items
     * @param $type
     * @param BaseIndex $index
     * @return Result
     * @throws Exception
     */
    public function doManipulate($items, $type, BaseIndex $index): Result
    {
        $client = $index->getClient();

        $update = $this->getUpdate($items, $type, $index, $client);
        // commit immediately when in dev mode
        if (Director::isDev()) {
            $update->addCommit();
        }

        return $client->update($update);
    }

    /**
     * get the update object ready
     *
     * @param SS_List $items
     * @param string $type
     * @param BaseIndex $index
     * @param \Solarium\Core\Client\Client $client
     * @return mixed
     * @throws Exception
     */
    protected function getUpdate($items, $type, BaseIndex $index, \Solarium\Core\Client\Client $client)
    {
        // get an update query instance
        $update = $client->createUpdate();

        switch ($type) {
            case static::DELETE_TYPE:
                // By pushing to a single array, we have less memory usage and no duplicates
                // This is faster, and more efficient, because we only do one DB query
                $delete = $items->map('ID', 'ClassName')->toArray();
                array_walk($delete, static function (&$item, $key) {
                    $item = sprintf('%s-%s', $item, $key);
                });
                $update->addDeleteByIds(array_values($delete));
                // Remove the deletion array from memory
                break;
            case static::DELETE_TYPE_ALL:
                $update->addDeleteQuery('*:*');
                break;
            case static::UPDATE_TYPE:
            case static::CREATE_TYPE:
                $this->updateIndex($index, $items, $update);
        }

        return $update;
    }

    /**
     * Create the documents and add to the update
     *
     * @param BaseIndex $index
     * @param SS_List $items
     * @param \Solarium\QueryType\Update\Query\Query $update
     * @throws Exception
     */
    public function updateIndex($index, $items, $update): void
    {
        $fields = $index->getFieldsForIndexing();
        $factory = $this->getFactory($items);
        $docs = $factory->buildItems($fields, $index, $update);
        if (count($docs)) {
            $update->addDocuments($docs);
        }
    }

    /**
     * Get the document factory prepared
     *
     * @param SS_List $items
     * @return DocumentFactory
     */
    protected function getFactory($items): DocumentFactory
    {
        $factory = Injector::inst()->get(DocumentFactory::class);
        $factory->setItems($items);
        $factory->setClass($items->first()->ClassName);
        $factory->setDebug($this->isInDebugMode());

        return $factory;
    }

    /**
     * Check if we are in debug mode
     *
     * @return bool
     */
    public function isInDebugMode(): bool
    {
        return $this->inDebugMode;
    }

    /**
     * Set the debug mode
     *
     * @param bool $inDebugMode
     * @return SolrCoreService
     */
    public function setInDebugMode(bool $inDebugMode): SolrCoreService
    {
        $this->inDebugMode = $inDebugMode;

        return $this;
    }

    /**
     * Check the Solr version to use
     *
     * @param HandlerStack|null $handler Used for testing the solr version
     * @return int
     */
    public function getSolrVersion($handler = null): int
    {
        $config = self::config()->get('config');
        $firstEndpoint = array_shift($config['endpoint']);
        $clientConfig = [
            'base_uri' => 'http://' . $firstEndpoint['host'] . ':' . $firstEndpoint['port'],
        ];

        if ($handler) {
            $clientConfig['handler'] = $handler;
        }

        $client = new GuzzleClient($clientConfig);

        $result = $client->get('solr/admin/info/system?wt=json');
        $result = json_decode($result->getBody(), 1);

        $version = version_compare('5.0.0', $result['lucene']['solr-spec-version']);

        return ($version > 0) ? 4 : 5;
    }

    /**
     * Get the client
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Set the client
     *
     * @param Client $client
     * @return SolrCoreService
     */
    public function setClient($client): SolrCoreService
    {
        $this->client = $client;

        return $this;
    }
}
