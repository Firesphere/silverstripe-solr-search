<?php
/**
 * Class SolrConfigureTask|Firesphere\SolrSearch\Tasks\SolrConfigureTask Configure Solr cores
 *
 * @package Firesphere\Solr\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Tasks;

use Exception;
use Firesphere\SolrSearch\Helpers\SolrLogger;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Interfaces\ConfigStore;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Stores\FileConfigStore;
use Firesphere\SolrSearch\Stores\PostConfigStore;
use Firesphere\SolrSearch\Traits\LoggerTrait;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ValidationException;
use Solarium\Exception\HttpException;

/**
 * Class SolrConfigureTask
 *
 * @package Firesphere\Solr\Search
 */
class SolrConfigureTask extends BuildTask
{
    use LoggerTrait;

    /**
     * @var string URLSegment
     */
    private static $segment = 'SolrConfigureTask';
    /**
     * @var string Title
     */
    protected $title = 'Configure Solr cores';
    /**
     * @var string Description
     */
    protected $description = 'Create or reload a Solr Core by adding or reloading a configuration.';

    /**
     * SolrConfigureTask constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request Current request
     * @return void
     * @throws HTTPException
     * @throws InvalidArgumentException
     * @throws ValidationException
     */
    public function run($request)
    {
        /** @var CacheInterface $cache */
        $cache = Injector::inst()->get(CacheInterface::class . '.SolrCache');
        $cache->delete('ValidClasses');
        $this->extend('onBeforeSolrConfigureTask', $request);

        $indexes = (new SolrCoreService())->getValidIndexes();

        foreach ($indexes as $index) {
            try {
                $this->configureIndex($index);
            } catch (Exception $error) {
                // @codeCoverageIgnoreStart
                $this->logException($index, $error);
                $this->getLogger()->error(sprintf('Core loading failed for %s', $index));
                $this->getLogger()->error($error->getMessage()); // in browser mode, it might not always show
                // Continue to the next index
                continue;
                // @codeCoverageIgnoreEnd
            }
            $this->extend('onAfterConfigureIndex', $index);
        }

        $this->extend('onAfterSolrConfigureTask');
        // Grab the latest logs
        $solrLogger = new SolrLogger();
        $solrLogger->saveSolrLog('Config');
    }

    /**
     * Update the index on the given store
     *
     * @param string $index Core to index
     */
    protected function configureIndex($index): void
    {
        /** @var BaseIndex $instance */
        $instance = Injector::inst()->get($index, false);

        $index = $instance->getIndexName();

        // Then tell Solr to use those config files
        /** @var SolrCoreService $service */
        $service = Injector::inst()->get(SolrCoreService::class);

        $configStore = $this->createConfigForIndex($instance);
        $method = $this->getMethod($index, $service);
        $service->$method($index, $configStore);
        $this->getLogger()->info(sprintf('Core %s successfully loaded', $index));
    }

    /**
     * Get the config and load it to Solr
     *
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
     * Get the store for the given config
     *
     * @param $storeConfig
     * @return ConfigStore
     */
    protected function getStore($storeConfig): ConfigStore
    {
        $configStore = Injector::inst()->create(ConfigStore::class, $storeConfig);

        // Allow changing the configStore if it needs to change to a different store
        $this->extend('onBeforeConfig', $configStore, $storeConfig);

        return $configStore;
    }

    /**
     * Figure out the method needed for the given core.
     *
     * @param $index
     * @param SolrCoreService $service
     * @return string
     */
    protected function getMethod($index, SolrCoreService $service): string
    {
        $status = $service->coreStatus($index);
        // Default to create
        $method = 'coreCreate';
        // Switch to reload if the core is loaded
        // Assuming a core that doesn't exist doesn't have uptime, as per Solr docs
        // And it has a start time.
        // You'd have to be pretty darn fast to hit 0 uptime and 0 starttime for an existing core!
        if ($status && ($status->getUptime() && $status->getStartTime() !== null)) {
            $method = 'coreReload';
        }

        return $method;
    }

    /**
     * Log an exception error
     *
     * @codeCoverageIgnore Can't be tested because of accessibility and the actual throw of exception
     * @param string $index Name of the index
     * @param Exception $error
     * @throws HTTPException
     * @throws ValidationException
     */
    private function logException($index, Exception $error): void
    {
        $this->getLogger()->error($error);
        $msg = sprintf(
            'Error loading core %s%s, Please log in to the CMS to find out more about Configuration errors %s',
            $index,
            PHP_EOL,
            PHP_EOL
        );
        SolrLogger::logMessage('ERROR', $msg);
    }
}
