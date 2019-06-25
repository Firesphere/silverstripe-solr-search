<?php


namespace Firesphere\SolrSearch\Tasks;

use Exception;
use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Helpers\SearchIntrospection;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;
use Solarium\Core\Client\Client;

class SolrIndexTask extends BuildTask
{
    /**
     * @var string
     */
    private static $segment = 'SolrIndexTask';

    /**
     * @var string
     */
    protected $title = 'Solr Index update';

    /**
     * @var string
     */
    protected $description = 'Add or update documents to an existing Solr core.';

    /**
     * @var SearchIntrospection
     */
    protected $introspection;

    /**
     * @var DocumentFactory
     */
    protected $factory;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var null|LoggerInterface
     */
    protected $logger;

    /**
     * @var Client
     */
    protected $client;

    /**
     * SolrIndexTask constructor. Sets up the document factory
     */
    public function __construct()
    {
        parent::__construct();
        // Only index live items.
        // The old FTS module also indexed Draft items. This is unnecessary
        Versioned::set_reading_mode(Versioned::DRAFT . '.' . Versioned::LIVE);
        $this->logger = Injector::inst()->get(LoggerInterface::class);


        $this->introspection = new SearchIntrospection();
    }

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return int|bool
     * @throws Exception
     * @todo clean up a bit, this is becoming a mess
     * @todo defer to background because it may run out of memory
     * @todo give Solr more time to respond
     * @todo Change the debug messaging to use the logger
     */
    public function run($request)
    {
        $startTime = time();
        $vars = $request->getVars();
        $indexes = ClassInfo::subclassesFor(BaseIndex::class);
        // If the given index is not an actual index, skip
        if (isset($vars['index']) && !in_array($vars['index'], $indexes, true)) {
            return false;
        }
        // If above doesn't fail, make the set var into an array to be indexed downstream, or continue with all indexes
        if (isset($vars['index']) && in_array($vars['index'], $indexes, true)) {
            $indexes = [$vars['index']];
        }
        // If all else fails, assume we're running a full index.

        $this->debug = isset($vars['debug']) || (Director::isDev() || Director::is_cli());

        $this->logger->info(date('Y-m-d H:i:s' . "\n"));
        $group = $request->getVar('group') ?: 0; // allow starting from a specific group
        $start = $request->getVar('start') ?: 0;

        $groups = 0;
        foreach ($indexes as $indexName) {
            // Skip the abstract base
            $ref = new ReflectionClass($indexName);
            if (!$ref->isInstantiable()) {
                continue;
            }

            $this->factory = Injector::inst()->get(DocumentFactory::class, false);
            $this->factory->setDebug($this->debug);
            /** @var BaseIndex $index */
            $index = Injector::inst()->get($indexName);
            $this->client = $index->getClient();

            // Only index the classes given in the var if needed, should be a single class
            $classes = isset($vars['class']) ? [$vars['class']] : $index->getClasses();

            // Set the start point to the requested value, if there is only one class to index
            if ($start > $group && count($classes) === 1) {
                $group = $start;
            }

            foreach ($classes as $class) {
                $isGroup = $request->getVar('group');
                [$groups, $group] = $this->reindexClass($isGroup, $class, $index, $group);
            }
        }
        $end = time();

        $this->logger->info(sprintf("It took me %d seconds to do all the indexing\n", ($end - $startTime)), []);
        $this->logger->info("done!\n", []);
        gc_collect_cycles(); // Garbage collection to prevent php from running out of memory

        return $groups;
    }

    /**
     * @param $isGroup
     * @param $class
     * @param BaseIndex $index
     * @param int $group
     * @return array
     * @throws Exception
     */
    protected function reindexClass($isGroup, $class, BaseIndex $index, int $group): array
    {
        $group = $group ?: 0;
        if ($this->debug) {
            $this->logger->info(sprintf('Indexing %s for %s', $class, $index->getIndexName()), []);
        }
        $count = 0;
        $groups = 0;
        $fields = $index->getFieldsForIndexing();
        // Run a single group
        if ($isGroup) {
            $this->doReindex($group, $class, $fields, $index, $count);
        } else {
            $batchLength = DocumentFactory::config()->get('batchLength');
            $groups = (int)ceil($class::get()->count() / $batchLength);
            // Otherwise, run them all
            while ($group <= $groups) { // Run from oldest to newest
                try {
                    [$count, $group] = $this->doReindex(
                        $group,
                        $class,
                        $fields,
                        $index,
                        $count
                    );
                } catch (RequestException $e) {
                    $this->logger->error($e->getResponse()->getBody());
                    if ($this->debug) {
                        $this->logger->error(date('Y-m-d H:i:s') . "\n", []);
                    }
                    gc_collect_cycles(); // Garbage collection to prevent php from running out of memory
                    $group++;
                    continue;
                }
            }
        }

        return [$groups, $group];
    }

    /**
     * @param int $group
     * @param string $class
     * @param array $fields
     * @param BaseIndex $index
     * @param int $count
     * @return array[int, int]
     * @throws Exception
     */
    protected function doReindex(
        $group,
        $class,
        array $fields,
        BaseIndex $index,
        $count = 0
    ): array {
        gc_collect_cycles(); // Garbage collection to prevent php from running out of memory
        $update = $this->getClient()->createUpdate();
        $this->factory->setItems(null);
        $this->factory->setClass($class);
        $docs = $this->factory->buildItems(
            $fields,
            $index,
            $update,
            $group,
            $count
        );
        // If there are no docs, no need to execute an action
        if (count($docs)) {
            $update->addDocuments($docs, true, Config::inst()->get(SolrCoreService::class, 'commit_within'));
            $update->addCommit();
            $this->client->update($update);
            // Clear out the docs when done
            foreach ($docs as $doc) {
                unset($doc);
            }
        }
        $group++;
        gc_collect_cycles(); // Garbage collection to prevent php from running out of memory

        return [$count, $group];
    }

    /**
     * @param Client $client
     * @return SolrIndexTask
     */
    public function setClient(Client $client): SolrIndexTask
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}
