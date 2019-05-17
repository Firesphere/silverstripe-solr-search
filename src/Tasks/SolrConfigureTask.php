<?php


namespace Firesphere\SearchConfig\Tasks;

use Exception;
use Firesphere\SearchConfig\Indexes\BaseIndex;
use Firesphere\SearchConfig\Interfaces\ConfigStore;
use Firesphere\SearchConfig\Services\SolrCoreService;
use Firesphere\SearchConfig\Stores\FileConfigStore;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\FullTextSearch\Utils\Logging\SearchLogFactory;

class SolrConfigureTask extends BuildTask
{
    private static $segment = 'SolrConfigureTask';

    protected $title = 'Configure or reload an existing Solr core';

    protected $description
        = 'Create or reload a Solr Core by adding or reloading a configuration.';

    protected $logger;

    public function __construct()
    {
        parent::__construct();
        $name = get_class($this);
        $verbose = Controller::curr()->getRequest()->getVar('verbose');

        // Set new logger
        $logger = $this
            ->getLoggerFactory()
            ->getOutputLogger($name, $verbose);
        $this->setLogger($logger);
    }

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return void
     * @throws ReflectionException
     */
    public function run($request)
    {
        $this->extend('onBeforeSolrConfigureTask', $request);

        $indexes = ClassInfo::subclassesFor(BaseIndex::class);
        foreach ($indexes as $instance) {
            $ref = new ReflectionClass($instance);
            if (!$ref->isInstantiable()) {
                continue;
            }
            $instance = singleton($instance);

            try {
                $this->updateIndex($instance);
            } catch (Exception $e) {
                // We got an exception. Warn, but continue to next index.
                $this
                    ->getLogger()
                    ->error('Failure: ' . $e->getMessage());
            }
        }

        if (isset($e)) {
            exit(1);
        }

        $this->extend('onAfterSolrConfigureTask', $request);
    }

    /**
     * @return SearchLogFactory
     */
    protected function getLoggerFactory()
    {
        return Injector::inst()->get(SearchLogFactory::class);
    }

    /**
     * Update the index on the given store
     *
     * @param BaseIndex $instance Instance
     */
    protected function updateIndex($instance)
    {
        $index = $instance->getIndexName();
        $this->getLogger()->info("Configuring $index.");

        // Upload the config files for this index
        $this->getLogger()->info('Uploading configuration ...');
        // @todo load from config
        $config = [
            'mode' => 'file',
            'path' => Director::baseFolder() . '/.solr'
        ];
        /** @var ConfigStore $configStore */
        $configStore = Injector::inst()->create(FileConfigStore::class, $config);
        $instance->uploadConfig($configStore);

        // Then tell Solr to use those config files
        /** @var SolrCoreService $service */
        $service = Injector::inst()->get(SolrCoreService::class);

        // Assuming a core that doesn't exist doesn't have uptime, as per Solr docs
        // And it has a start time.
        // You'd have to be pretty darn fast to hit 0 uptime and 0 starttime for an existing core!
        $status = $service->coreStatus($index);
        if ($status && ($status->getUptime() && $status->getStartTime() !== null)) {
            $this->getLogger()->info('Reloading core ...');
            $service->coreReload($index);
        } else {
            $this->getLogger()->info('Creating core ...');
            $service->coreCreate($index, $configStore->instanceDir($index));
        }

        $this->getLogger()->info('Done');
    }

    /**
     * Get the monolog logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Assign a new logger
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
