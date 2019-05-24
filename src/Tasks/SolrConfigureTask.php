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
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;

class SolrConfigureTask extends BuildTask
{
    private static $segment = 'SolrConfigureTask';

    protected $title = 'Configure Solr cores';

    protected $description = 'Create or reload a Solr Core by adding or reloading a configuration.';

    /**
     * @todo load the logger :)
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct()
    {
        parent::__construct();
        // @todo add logger
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
            $instance = Injector::inst()->createWithArgs($instance, [true]);

            try {
                $this->updateIndex($instance);
            } catch (Exception $e) {
                // We got an exception. Warn, but continue to next index.
                var_dump($e);
            }
        }

        if (isset($e)) {
            exit(1);
        }

        $this->extend('onAfterSolrConfigureTask', $request);
    }

    /**
     * Update the index on the given store
     *
     * @todo make this a tad cleaner, it's a bit unreadable
     * @param BaseIndex $instance Instance
     */
    protected function updateIndex($instance)
    {
        $index = $instance->getIndexName();

        // @todo load from config
        $config = [
            'mode' => 'file',
            'path' => Director::baseFolder() . '/.solr'
        ];
        /** @todo make stores configurable */
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
            try {
                $service->coreReload($index);
            } catch (Exception $e) {
                var_dump($e);
                // Possibly a file error, try to unload and recreate the core
                $service->coreUnload($index);
                $service->coreCreate($index, $configStore->instanceDir($index));
            }
        } else {
            $service->coreCreate($index, $configStore->instanceDir($index));
        }
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
