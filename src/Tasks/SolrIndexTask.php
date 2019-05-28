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

    public function __construct()
    {
        parent::__construct();
        $this->factory = Injector::inst()->get(DocumentFactory::class);
    }

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return int
     * @throws Exception
     * @todo make this properly use groups and maybe background tasks
     * @todo add a queued job
     */
    public function run($request)
    {
        $debug = $request->getVar('debug') ? true : false;
        $debug = Director::isDev() || $debug;

        print_r(date('Y-m-d H:i:s' . "\n"));
        $start = time();
        // Only index live items.
        // The old FTS module also indexed Draft items. This is unnecessary
        Versioned::set_reading_mode(Versioned::DRAFT . '.' . Versioned::LIVE);

        $this->introspection = new SearchIntrospection();

        $indexes = ClassInfo::subclassesFor(BaseIndex::class);

        foreach ($indexes as $index) {

            // Skip the abstract base
            $ref = new ReflectionClass($index);
            if (!$ref->isInstantiable()) {
                continue;
            }

            /** @var BaseIndex $index */
            $index = Injector::inst()->get($index);
            $classes = $index->getClass();
            $client = $index->getClient();

            $groups = 0;
            foreach ($classes as $class) {
                $batchLength = DocumentFactory::config()->get('batchLength');
                // @todo make sure the total amount of groups is all classes combined
                $groups += ($class::get()->count() / $batchLength);
            }


            foreach ($classes as $class) {
                if ($debug) {
                    Debug::message(sprintf('Indexing %s for %s', $class, $index->getIndexName()), false);
                }
                $group = $request->getVar('group') ?: $groups; // allow starting from a specific group
                $count = 0;
                $fields = array_merge(
                    $index->getFulltextFields(),
                    $index->getSortFields(),
                    $index->getFilterFields()
                );
                // Run a single group
                if ($request->getVar('group')) {
                    Debug::message(sprintf('Indexing group %s out of %s', $group, $groups));
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
                // Otherwise, run them all
                } else {
                    while ($group <= $groups) { // Run from newest to oldest item
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
        $update->addDocuments($docs, true, 10);
        $client->update($update);
        $update = null; // clear out the update set for memory reasons
        $group++;
        Debug::message(date('Y-m-d H:i:s' . "\n"), false);
        gc_collect_cycles(); // Garbage collection to prevent php from running out of memory

        return [$count, $group];
    }
}
