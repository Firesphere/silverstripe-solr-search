<?php


namespace Firesphere\SolrSearch\Tasks;

use Exception;
use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Helpers\SolrLogger;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\States\SiteState;
use Firesphere\SolrSearch\Traits\LoggerTrait;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use ReflectionException;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;

/**
 * Class SolrIndexTask
 *
 * @description Index items to Solr through a tasks
 * @package Firesphere\SolrSearch\Tasks
 */
class SolrIndexTask extends BuildTask
{
    use LoggerTrait;
    /**
     * URLSegment of this task
     *
     * @var string
     */
    private static $segment = 'SolrIndexTask';
    /**
     * Store the current states for all instances of SiteState
     *
     * @var array
     */
    public $currentStates;
    /**
     * My name
     *
     * @var string
     */
    protected $title = 'Solr Index update';
    /**
     * What do I do?
     *
     * @var string
     */
    protected $description = 'Add or update documents to an existing Solr core.';
    /**
     * Debug mode enabled, default false
     *
     * @var bool
     */
    protected $debug = false;
    /**
     * Singleton of {@link SolrCoreService}
     *
     * @var SolrCoreService
     */
    protected $service;

    /**
     * SolrIndexTask constructor. Sets up the document factory
     *
     * @throws ReflectionException
     */
    public function __construct()
    {
        parent::__construct();
        // Only index live items.
        // The old FTS module also indexed Draft items. This is unnecessary
        Versioned::set_reading_mode(Versioned::DEFAULT_MODE);
        $this->setService(Injector::inst()->get(SolrCoreService::class));
        $this->setLogger(Injector::inst()->get(LoggerInterface::class));
        $this->setDebug(Director::isDev() || Director::is_cli());
        $currentStates = SiteState::currentStates();
        SiteState::setDefaultStates($currentStates);
    }

    /**
     * Set the {@link SolrCoreService}
     *
     * @param SolrCoreService $service
     * @return SolrIndexTask
     */
    public function setService(SolrCoreService $service): SolrIndexTask
    {
        $this->service = $service;

        return $this;
    }

    /**
     * Set the debug mode
     *
     * @param bool $debug
     * @return SolrIndexTask
     */
    public function setDebug(bool $debug): SolrIndexTask
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return int|bool
     * @throws Exception
     * @throws GuzzleException
     * @todo defer to background because it may run out of memory
     */
    public function run($request)
    {
        $startTime = time();
        list($vars, $group, $isGroup) = $this->taskSetup($request);
        $groups = 0;
        $indexes = $this->service->getValidIndexes($request->getVar('index'));

        foreach ($indexes as $indexName) {
            /** @var BaseIndex $index */
            $index = Injector::inst()->get($indexName, false);

            $indexClasses = $index->getClasses();
            $classes = $this->getClasses($vars, $indexClasses);
            if (!count($classes)) {
                continue;
            }

            $this->clearIndex($vars, $index);

            $groups = $this->indexClassForIndex($classes, $isGroup, $index, $group);
        }
        $this->getLogger()->info(
            sprintf('It took me %d seconds to do all the indexing%s', (time() - $startTime), PHP_EOL)
        );
        // Grab the latest logs from indexing if needed
        $solrLogger = new SolrLogger();
        $solrLogger->saveSolrLog('Config');

        return $groups;
    }

    /**
     * Set up the requirements for this task
     *
     * @param HTTPRequest $request
     * @return array
     */
    protected function taskSetup($request): array
    {
        $vars = $request->getVars();
        $this->debug = $this->debug || isset($vars['debug']);
        $group = $vars['group'] ?? 0;
        $start = $vars['start'] ?? 0;
        $group = ($start > $group) ? $start : $group;
        $isGroup = isset($vars['group']);

        return [$vars, $group, $isGroup];
    }

    /**
     * get the classes to run for this task execution
     *
     * @param $vars
     * @param array $classes
     * @return bool|array
     */
    protected function getClasses($vars, array $classes): array
    {
        if (isset($vars['class'])) {
            return array_intersect($classes, [$vars['class']]);
        }

        return $classes;
    }

    /**
     * Clear the given index if a full re-index is needed
     *
     * @param $vars
     * @param BaseIndex $index
     * @throws Exception
     */
    public function clearIndex($vars, BaseIndex $index)
    {
        if (!empty($vars['clear'])) {
            $this->getLogger()->info(sprintf('Clearing index %s', $index->getIndexName()));
            $this->service->doManipulate(ArrayList::create([]), SolrCoreService::DELETE_TYPE_ALL, $index);
        }
    }

    /**
     * Index the classes for a specific index
     *
     * @param $classes
     * @param $isGroup
     * @param BaseIndex $index
     * @param $group
     * @return int
     * @throws Exception
     * @throws GuzzleException
     */
    protected function indexClassForIndex($classes, $isGroup, BaseIndex $index, $group): int
    {
        $groups = 0;
        foreach ($classes as $class) {
            $groups = $this->indexClass($isGroup, $class, $index, $group);
        }

        return $groups;
    }

    /**
     * Index a single class for a given index. {@link static::indexClassForIndex()}
     *
     * @param bool $isGroup
     * @param string $class
     * @param BaseIndex $index
     * @param int $group
     * @return int
     * @throws GuzzleException
     * @throws ValidationException
     */
    private function indexClass($isGroup, $class, BaseIndex $index, int $group): int
    {
        $this->getLogger()->info(sprintf('Indexing %s for %s', $class, $index->getIndexName()), []);

        $batchLength = DocumentFactory::config()->get('batchLength');
        $groups = (int)ceil($class::get()->count() / $batchLength);
        $groups = $isGroup ? $group : $groups;
        while ($group <= $groups) { // Run from oldest to newest
            try {
                $this->doReindex($group, $class, $batchLength, $index);
            } catch (Exception $error) {
                $this->logException($index->getIndexName(), $group, $error);
                $group++;
                continue;
            }
            $group++;
            $this->getLogger()->info(sprintf('Indexed group %s', $group));
        }

        return $groups;
    }

    /**
     * Reindex the given group, for each state
     *
     * @param int $group
     * @param string $class
     * @param int $batchLength
     * @param BaseIndex $index
     * @throws Exception
     */
    private function doReindex($group, $class, $batchLength, BaseIndex $index): void
    {
        foreach (SiteState::getStates() as $state) {
            if ($state !== 'default') {
                SiteState::withState($state);
            }
            $this->stateReindex($group, $class, $batchLength, $index);
        }

        SiteState::withState(SiteState::DEFAULT_STATE);
    }

    /**
     * Index a group of a class for a specific state and index
     *
     * @param $group
     * @param $class
     * @param $batchLength
     * @param BaseIndex $index
     * @throws Exception
     */
    private function stateReindex($group, $class, $batchLength, BaseIndex $index): void
    {
        // Generate filtered list of local records
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        /** @var DataList|DataObject[] $items */
        $items = DataObject::get($baseClass)
            ->sort('ID ASC')
            ->limit($batchLength, ($group * $batchLength));
        if ($items->count()) {
            $this->updateIndex($index, $items);
        }
    }

    /**
     * Execute the update on the client
     *
     * @param BaseIndex $index
     * @param $items
     * @throws Exception
     */
    private function updateIndex(BaseIndex $index, $items): void
    {
        $client = $index->getClient();
        $update = $client->createUpdate();
        $this->service->setInDebugMode($this->debug);
        $this->service->updateIndex($index, $items, $update);
        $update->addCommit();
        $client->update($update);
    }

    /**
     * Log an exception if it happens. Most are catched, these logs are for the developers
     * to identify problems and fix them.
     *
     * @param string $index
     * @param int $group
     * @param Exception $exception
     * @throws GuzzleException
     * @throws ValidationException
     */
    private function logException($index, int $group, Exception $exception): void
    {
        $this->getLogger()->error($exception->getMessage());
        $msg = sprintf(
            'Error indexing core %s on group %s,' . PHP_EOL .
            'Please log in to the CMS to find out more about Indexing errors' . PHP_EOL,
            $index,
            $group
        );
        SolrLogger::logMessage('ERROR', $msg, $index);
    }
}
