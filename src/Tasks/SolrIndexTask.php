<?php


namespace Firesphere\SearchConfig\Tasks;

use Exception;
use Firesphere\SearchConfig\Factories\DocumentFactory;
use Firesphere\SearchConfig\Helpers\SearchIntrospection;
use Firesphere\SearchConfig\Indexes\BaseIndex;
use Firesphere\SearchConfig\Services\SolrCoreService;
use ReflectionClass;
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
     */
    public function run($request)
    {
        print_r(date('Y-m-d H:i:s' . "\n"));
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
            $config['endpoint']['localhost']['core'] = $index->getIndexName();
            $client = new Client($config);

            $update = $client->createUpdate();

            $classes = $index->getClass();

            foreach ($classes as $class) {
                $fields = array_merge(
                    $index->getFulltextFields(),
                    $index->getSortFields(),
                    $index->getFilterFields()
                );
                $docs = $this->factory->buildItems($class, array_unique($fields), $index, $update);
                $update->addDocuments($docs, true);
                $update->addCommit();
                $client->update($update);
            }
        }
        print_r(date('Y-m-d H:i:s' . "\n"));
        print_r("done!\n");
    }
}
