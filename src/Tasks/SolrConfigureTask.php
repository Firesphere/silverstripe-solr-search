<?php


namespace Firesphere\SolrSearch\Tasks;

use Exception;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Interfaces\ConfigStore;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Stores\FileConfigStore;
use Firesphere\SolrSearch\Stores\PostConfigStore;
use GuzzleHttp\Exception\RequestException;
use LogicException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;

class SolrConfigureTask extends BuildTask
{
    private static $segment = 'SolrConfigureTask';

    protected $title = 'Configure Solr cores';

    protected $description = 'Create or reload a Solr Core by adding or reloading a configuration.';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct()
    {
        parent::__construct();
        $this->setLogger($this->getLoggerFactory());
    }

    /**
     * @return LoggerInterface log channel
     */
    protected function getLoggerFactory(): LoggerInterface
    {
        return Injector::inst()->get(LoggerInterface::class);
    }

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return bool|Exception
     * @throws ReflectionException
     */
    public function run($request)
    {
        $this->extend('onBeforeSolrConfigureTask', $request);

        $indexes = ClassInfo::subclassesFor(BaseIndex::class);
        foreach ($indexes as $index) {
            $ref = new ReflectionClass($index);
            if (!$ref->isInstantiable()) {
                continue;
            }
            /** @var BaseIndex $instance */
            $instance = Injector::inst()->get($index);

            try {
                $this->configureIndex($instance);
                $this->extend('onAfterSolrConfigureTask', $request);
            } catch (RequestException $e) {
                $this->logger->error($e->getResponse()->getBody());
                throw new RuntimeException($e);
            }
        }

        return true;
    }

    /**
     * Update the index on the given store
     *
     * @param BaseIndex $instance Instance
     */
    protected function configureIndex($instance): void
    {
        $index = $instance->getIndexName();

        $storeConfig = SolrCoreService::config()->get('store');
        $configStore = $this->getStore($storeConfig);

        // Then tell Solr to use those config files
        /** @var SolrCoreService $service */
        $service = Injector::inst()->get(SolrCoreService::class);

        // Assuming a core that doesn't exist doesn't have uptime, as per Solr docs
        // And it has a start time.
        // You'd have to be pretty darn fast to hit 0 uptime and 0 starttime for an existing core!
        $status = $service->coreStatus($index);
        $instance->uploadConfig($configStore);
        // I don't want to turn this in to an endless try-catch, so lets just break at level 2
        if ($status && ($status->getUptime() && $status->getStartTime() !== null)) {
            try {
                $service->coreReload($index);
                $this->logger->info(sprintf('Core %s successfully reloaded', $index));
            } catch (RequestException $e) {
                // A common scenario, a reload fails at first try, hence a double run
                try {
                    $service->coreCreate($index, $configStore);
                    $this->logger->info(sprintf('Core %s successfully loaded', $index));
                } catch (RequestException $e) {
                    $this->logger->error(sprintf('Error attempting to reload core %s', $index));
                    $this->logger->error($e->getResponse()->getBody());
                    throw new RuntimeException($e);
                }
            }
        } else {
            try {
                $service->coreCreate($index, $configStore);
                $this->logger->info(sprintf('Core %s successfully created', $index));
            } catch (RequestException $e) {
                // A common scenario, a create fails at first try, hence a double run
                // Most commonly, this happens when an existing core in FileStore mode is attempted
                // to be reloaded again, but hasn't been loaded in to Solr yet (e.g. Solr restart)
                try {
                    $service->coreCreate($index, $configStore);
                    $this->logger->info(sprintf('Core %s successfully created', $index));
                } catch (RequestException $e) {
                    $this->logger->error(sprintf('Failed creating core %s', $index));
                    $this->logger->error($e->getResponse()->getBody());
                    throw new RuntimeException($e);
                }
            }
        }
    }

    /**
     * @param $storeConfig
     * @return ConfigStore
     */
    protected function getStore($storeConfig): ConfigStore
    {
        $configStore = null;

        /** @var ConfigStore $configStore */
        if ($storeConfig['mode'] === 'post') {
            $configStore = Injector::inst()->create(PostConfigStore::class, $storeConfig);
        } elseif ($storeConfig['mode'] === 'file') {
            // A relative folder should be rewritten to a writeable folder for the system
            if (Director::is_relative_url($storeConfig['path'])) {
                $storeConfig['path'] = Director::baseFolder() . '/' . $storeConfig['path'];
            }
            $configStore = Injector::inst()->create(FileConfigStore::class, $storeConfig);
        }

        // Allow changing the configStore if it needs to change to a different store
        $this->extend('onBeforeConfig', $configStore, $storeConfig);

        if (!$configStore) {
            throw new LogicException('No functional config store found');
        }

        return $configStore;
    }

    /**
     * Get the monolog logger
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Assign a new logger
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
