<?php


namespace Firesphere\SolrSearch\Tasks;

use Exception;
use Firesphere\SolrSearch\Helpers\SolrLogger;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Interfaces\ConfigStore;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Stores\FileConfigStore;
use Firesphere\SolrSearch\Stores\PostConfigStore;
use Firesphere\SolrSearch\Traits\LoggerTrait;
use GuzzleHttp\Exception\GuzzleException;
use ReflectionException;
use RuntimeException;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ValidationException;

/**
 * Class SolrConfigureTask
 * @package Firesphere\SolrSearch\Tasks
 */
class SolrConfigureTask extends BuildTask
{
    use LoggerTrait;

    protected static $storeModes = [
        'file' => FileConfigStore::class,
        'post' => PostConfigStore::class,
//        'webdav' => WebdavConfigStore::class,
    ];
    private static $segment = 'SolrConfigureTask';
    protected $title = 'Configure Solr cores';
    protected $description = 'Create or reload a Solr Core by adding or reloading a configuration.';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return bool|Exception
     * @throws ReflectionException
     * @throws ValidationException
     * @throws GuzzleException
     */
    public function run($request)
    {
        $this->extend('onBeforeSolrConfigureTask', $request);

        $indexes = (new SolrCoreService())->getValidIndexes();

        foreach ($indexes as $index) {
            try {
                $this->configureIndex($index);
            } catch (Exception $error) {
                $this->getLogger()->error(sprintf('Core loading failed for %s', $index));
                // Continue to the next index
                continue;
            }
            $this->extend('onAfterConfigureIndex', $index);
        }

        $this->extend('onAfterSolrConfigureTask');
        // Grab the latest logs
        $solrLogger = new SolrLogger();
        $solrLogger->saveSolrLog('Config');

        return true;
    }

    /**
     * Update the index on the given store
     *
     * @param string $index
     * @throws ValidationException
     * @throws GuzzleException
     */
    protected function configureIndex($index): void
    {
        /** @var BaseIndex $instance */
        $instance = Injector::inst()->get($index, false);

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
            $this->getLogger()->info(sprintf('Core %s successfully loaded', $index));
        } catch (Exception $error) {
            $this->logException($index, $error);
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
        $store = static::$storeModes[$storeConfig['mode']];
        $configStore = Injector::inst()->create($store, $storeConfig);

        // Allow changing the configStore if it needs to change to a different store
        $this->extend('onBeforeConfig', $configStore, $storeConfig);

        return $configStore;
    }

    /**
     * @param $index
     * @param Exception $error
     * @throws GuzzleException
     * @throws ValidationException
     */
    private function logException($index, Exception $error): void
    {
        $this->getLogger()->error($error);
        $msg = sprintf(
            'Error loading core %s,' . PHP_EOL .
            'Please log in to the CMS to find out more about Configuration errors' . PHP_EOL .
            'Last known error:',
            $index
        );
        SolrLogger::logMessage('ERROR', $msg, $index);
    }
}
