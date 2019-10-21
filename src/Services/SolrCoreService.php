<?php

namespace Firesphere\SolrSearch\Services;

use Exception;
use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Helpers\FieldResolver;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Traits\CoreAdminTrait;
use Firesphere\SolrSearch\Traits\CoreServiceTrait;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use LogicException;
use ReflectionClass;
use ReflectionException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\SS_List;
use Solarium\Client;
use Solarium\Core\Client\Adapter\Guzzle;
use Solarium\Core\Client\Client as CoreClient;
use Solarium\Plugin\BufferedAdd\BufferedAdd;
use Solarium\Plugin\BufferedAdd\Event\Events;
use Solarium\Plugin\BufferedAdd\Event\PreFlush as PreFlushEvent;
use Solarium\QueryType\Update\Query\Query;
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
    use Configurable;
    use CoreServiceTrait;
    use CoreAdminTrait;
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

    /**
     * @var array Base indexes that exist
     */
    protected $baseIndexes = [];
    /**
     * @var array Valid indexes out of the base indexes
     */
    protected $validIndexes = [];

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
            $index = Injector::inst()->get($indexString, false);
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
     * Execute the manipulation of solr documents
     *
     * @param SS_List $items
     * @param string $type
     * @param BaseIndex $index
     * @return Result
     * @throws Exception
     */
    public function doManipulate($items, $type, BaseIndex $index): Result
    {
        $client = $index->getClient();

        $update = $this->doUpdate($items, $type, $index, $client);

        return $client->update($update);
    }

    /**
     * get the update object ready
     *
     * @param SS_List $items
     * @param string $type
     * @param BaseIndex $index
     * @param CoreClient $client
     * @return Query
     * @throws Exception
     */
    protected function doUpdate($items, $type, BaseIndex $index, CoreClient $client)
    {
        $update = $client->createUpdate();
        switch ($type) {
            case static::DELETE_TYPE:
                // By pushing to a single array, we have less memory usage and no duplicates
                // This is faster, and more efficient, because we only do one DB query
                // get an update query instance
                $delete = $items->map('ID', 'ClassName')->toArray();
                array_walk(
                    $delete,
                    static function (&$item, $key) {
                        $item = sprintf('%s-%s', $item, $key);
                    }
                );
                $update->addDeleteByIds(array_values($delete));
                break;
            case static::DELETE_TYPE_ALL:
                // get an update query instance
                $update->addDeleteQuery('*:*');
                break;
            case static::UPDATE_TYPE:
            case static::CREATE_TYPE:
                $this->updateIndex($index, $items);
        }

        $update->addCommit();

        return $update;
    }

    /**
     * Create the documents and add to the update
     *
     * @param BaseIndex $index
     * @param SS_List $items
     * @throws Exception
     */
    public function updateIndex($index, $items): void
    {
        $client = $index->getClient();
        /** @var BufferedAdd $bufferAdd */
        $bufferAdd = $client->getPlugin('bufferedadd');
        $update = $client->createUpdate();
        $fields = $index->getFieldsForIndexing();
        $factory = $this->getFactory($items);
        if ($this->isInDebugMode()) {
            $this->debugEvent($index);
        }
        $factory->buildItems($fields, $index, $update, $bufferAdd);
        $bufferAdd->flush();
    }

    /**
     * Get the document factory prepared
     *
     * @param SS_List $items
     * @return DocumentFactory
     */
    protected function getFactory($items): DocumentFactory
    {
        $factory = Injector::inst()->get(DocumentFactory::class, false);
        $factory->setItems($items);
        $factory->setClass($items->first()->ClassName);
        $factory->setDebug($this->isInDebugMode());

        return $factory;
    }

    /**
     * @param BaseIndex $index
     */
    protected function debugEvent($index): void
    {
        $index->getClient()->getEventDispatcher()->addListener(
            Events::PRE_FLUSH,
            function (PreFlushEvent $event) use ($index) {
                $ids = [];
                foreach ($event->getBuffer() as $doc) {
                    $ids[] = $doc->getFields()['ObjectID'];
                }
                $debugString = sprintf('Adding docs to %s%s', $index->getIndexName(), PHP_EOL);
                Debug::message(sprintf('%s[%s]', $debugString, implode(',', $ids)), false);
            }
        );
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
}
