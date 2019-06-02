<?php


namespace Firesphere\SolrSearch\Tasks;

use Exception;
use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Helpers\SearchIntrospection;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;
use ReflectionClass;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;
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
     * SolrIndexTask constructor. Sets up the document factory
     */
    public function __construct()
    {
        parent::__construct();
        $this->factory = Injector::inst()->get(DocumentFactory::class);
        // Only index live items.
        // The old FTS module also indexed Draft items. This is unnecessary
        Versioned::set_reading_mode(Versioned::DRAFT . '.' . Versioned::LIVE);

        $this->introspection = new SearchIntrospection();
    }

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return int
     * @throws Exception
     * @todo clean up a bit, this is becoming a mess
     * @todo defer to background because it may run out of memory
     * @todo give Solr more time to respond
     */
    public function run($request)
    {
        $start = time();
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

        $debug = isset($vars['debug']) ? true : false;
        // Debug if in dev or CLI, or debug is requested explicitly
        $debug = (Director::isDev() || Director::is_cli()) || $debug;

        Debug::message(date('Y-m-d H:i:s' . "\n"));

        $groups = 0;
        foreach ($indexes as $index) {
            // Skip the abstract base
            $ref = new ReflectionClass($index);
            if (!$ref->isInstantiable()) {
                continue;
            }

            /** @var BaseIndex $index */
            $index = Injector::inst()->get($index);

            // Only index the classes given in the var if needed, should be a single class
            $classes = isset($vars['class']) ? [$vars['class']] : $index->getClass();

            $client = $index->getClient();
            $group = $request->getVar('group') ?: 0; // allow starting from a specific group
            $start = $request->getVar('start') ?: 0;
            // Set the start point to the requested value, if there is only one class to index
            if ($start > $group && count($classes) === 1) {
                $group = $start;
            }

            foreach ($classes as $class) {
                $batchLength = DocumentFactory::config()->get('batchLength');
                $groups = (int)ceil($class::get()->count() / $batchLength);
                if ($debug) {
                    Debug::message(sprintf('Indexing %s for %s', $class, $index->getIndexName()), false);
                }
                $count = 0;
                $fields = $index->getFieldsForIndexing();
                // Run a single group
                if ($request->getVar('group')) {
                    list($count, $group) = $this->doReindex(
                        $group,
                        $groups,
                        $client,
                        $class,
                        $fields,
                        $index,
                        $count,
                        $debug
                    );
                } else {
                    // Otherwise, run them all
                    while ($group <= $groups) { // Run from newest to oldest item
                        try {
                            list($count, $group) = $this->doReindex(
                                $group,
                                $groups,
                                $client,
                                $class,
                                $fields,
                                $index,
                                $count,
                                $debug
                            );
                        } catch (Exception $e) {
                            // get an update query instance
                            $update = $client->createUpdate();
                            // optimize the index
                            $update->addOptimize(true, false, 5);
                            $client->update($update);
                            $update = null; // clear out the update set for memory reasons
                            Debug::message(date('Y-m-d H:i:s' . "\n"), false);
                            gc_collect_cycles(); // Garbage collection to prevent php from running out of memory

                            continue;
                        }
                    }
                    // Reset the group for the next class
                    if ($group >= $groups) {
                        $group = 0;
                    }
                }
            }
        }
        $end = time();

        Debug::message(sprintf("It took me %d seconds to do all the indexing\n", ($end - $start)), false);
        Debug::message("done!\n", false);
        gc_collect_cycles(); // Garbage collection to prevent php from running out of memory

        return $groups;
    }

    /**
     * @param int $group
     * @param int $groups
     * @param Client $client
     * @param string $class
     * @param array $fields
     * @param BaseIndex $index
     * @param int $count
     * @param bool $debug
     * @return array[int, int]
     * @throws Exception
     */
    protected function doReindex(
        $group,
        $groups,
        Client $client,
        $class,
        array $fields,
        BaseIndex $index,
        &$count,
        $debug
    ) {
        Debug::message(sprintf('Indexing %s group of %s', $group, $groups), false);
        $update = $client->createUpdate();
        $docs = $this->factory->buildItems($class, array_unique($fields), $index, $update, $group, $count, $debug);
        $update->addDocuments($docs, true, Config::inst()->get(SolrCoreService::class, 'commit_within'));
        $client->update($update);
        $group++;
        // get an update query instance
        $update = $client->createUpdate();
        // optimize the index
        $update->addOptimize(true, false, 5);
        $client->update($update);
        $update = null; // clear out the update set for memory reasons
        Debug::message(date('Y-m-d H:i:s' . "\n"), false);
        gc_collect_cycles(); // Garbage collection to prevent php from running out of memory

        return [$count, $group];
    }
}
