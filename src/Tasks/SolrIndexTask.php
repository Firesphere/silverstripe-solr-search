<?php


namespace Firesphere\SearchConfig\Tasks;

use Exception;
use Firesphere\SearchConfig\Factories\DocumentFactory;
use Firesphere\SearchConfig\Helpers\SearchIntrospection;
use Firesphere\SearchConfig\Indexes\BaseIndex;
use Firesphere\SearchConfig\Services\SolrCoreService;
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
            $config = Config::inst()->get(SolrCoreService::class, 'config');
            $config['endpoint'] = $index->getConfig($config['endpoint']);
            $config['timeout'] = 10000;

            $classes = $index->getClass();
            $client = new Client($config);


            foreach ($classes as $class) {
                if ($debug) {
                    Debug::message(sprintf('Indexing %s for %s', $class, $index->getIndexName()));
                }
                $groups = ceil($class::get()->count() / 2500);
                $group = 0;
                $fields = array_merge(
                    $index->getFulltextFields(),
                    $index->getSortFields(),
                    $index->getFilterFields()
                );
                while ($group <= $groups) {
                    $update = $client->createUpdate();
                    $docs = $this->factory->buildItems($class, array_unique($fields), $index, $update, $group, $debug);
                    $update->addDocuments($docs, true, 10);
                    $client->update($update);
                    $update = null;
                    $group++;
                    print_r(date('Y-m-d H:i:s' . "\n"));
                }
            }
        }
        $end = time();

        Debug::message("It took me %s seconds to do all the indexing\n", ($end - $start), false);
        print_r("done!\n");
    }
}
