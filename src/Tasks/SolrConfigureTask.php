<?php


namespace Firesphere\SolrSearch\Tasks;

use Exception;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Interfaces\ConfigStore;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Stores\FileConfigStore;
use Firesphere\SolrSearch\Stores\PostConfigStore;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use ReflectionException;
use RuntimeException;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;

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

        $indexes = (new SolrCoreService())->getValidIndexes();

        foreach ($indexes as $index) {
            try {
                $this->configureIndex($index);
            } catch (RequestException $error) {
                $exception = json_decode($error->getResponse()->getBody()->read(999999));
                $this->logger->error($exception);
                $this->logger->error(sprintf('Core loading failed for %s', $index));
                // Continue to the next index
                continue;
            }
            $this->extend('onAfterConfigureIndex', $index);
        }

        $this->extend('onAfterSolrConfigureTask');

        return true;
    }

    /**
     * Update the index on the given store
     *
     * @param string $index
     */
    protected function configureIndex($index): void
    {
        /** @var BaseIndex $instance */
        $instance = Injector::inst()->get($index);

        $index = $instance->getIndexName();

        // Then tell Solr to use those config files
        /** @var SolrCoreService $service */
        $service = Injector::inst()->get(SolrCoreService::class);

        // Assuming a core that doesn't exist doesn't have uptime, as per Solr docs
        // And it has a start time.
        // You'd have to be pretty darn fast to hit 0 uptime and 0 starttime for an existing core!
        $status = $service->coreStatus($index);
        $configStore = $this->createConfigForIndex($instance);
        // Default to create
        $method = 'coreCreate';
        // Switch to reload if the core is loaded
        if ($status && ($status->getUptime() && $status->getStartTime() !== null)) {
            $method = 'coreReload';
        }
        try {
            $service->$method($index, $configStore);
            $this->logger->info(sprintf('Core %s successfully loaded', $index));
        } catch (RequestException $error) {
            throw new RuntimeException($error);
        }
    }

    /**
     * @param BaseIndex $instance
     * @return ConfigStore
     */
    protected function createConfigForIndex(BaseIndex $instance): ConfigStore
    {
        $storeConfig = SolrCoreService::config()->get('store');
        $configStore = $this->getStore($storeConfig);
        $instance->uploadConfig($configStore);

        return $configStore;
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
            $configStore = Injector::inst()->create(FileConfigStore::class, $storeConfig);
        }// elseif ($storeConfig['mode'] === 'webdav') {
        // @todo Add webdav store
        //}

        // Allow changing the configStore if it needs to change to a different store
        $this->extend('onBeforeConfig', $configStore, $storeConfig);

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
