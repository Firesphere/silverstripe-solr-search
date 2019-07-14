<?php


namespace Firesphere\SolrSearch\Tasks;

use Exception;
use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

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
     * @var bool
     */
    protected $debug = false;

    /**
     * @var null|LoggerInterface
     */
    protected $logger;

    /**
     * @var SolrCoreService
     */
    protected $service;

    /**
     * SolrIndexTask constructor. Sets up the document factory
     */
    public function __construct()
    {
        parent::__construct();
        // Only index live items.
        // The old FTS module also indexed Draft items. This is unnecessary
        Versioned::set_reading_mode(Versioned::DRAFT . '.' . Versioned::LIVE);
        $this->setService(Injector::inst()->get(SolrCoreService::class));
        $this->setLogger(Injector::inst()->get(LoggerInterface::class));
        $this->setDebug(Director::isDev() || Director::is_cli());
    }

    /**
     * @param SolrCoreService $service
     * @return SolrIndexTask
     */
    public function setService(SolrCoreService $service): SolrIndexTask
    {
        $this->service = $service;

        return $this;
    }

    /**
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
     * @todo defer to background because it may run out of memory
     */
    public function run($request)
    {
        $startTime = time();
        [$vars, $group, $start, $isGroup] = $this->taskSetup($request);
        $indexes = $this->service->getValidIndexes($request->getVar('index'));
        $this->getLogger()->info(date('Y-m-d H:i:s') . PHP_EOL);

        $groups = 0;
        foreach ($indexes as $indexName) {
            /** @var BaseIndex $index */
            $index = Injector::inst()->get($indexName);

            if (!empty($vars['clear'])) {
                $this->getLogger()->info(sprintf('Clearing index %s', $indexName));
                $this->service->doManipulate(ArrayList::create([]), SolrCoreService::DELETE_TYPE_ALL, $index);
            }

            // Only index the classes given in the var if needed, should be a single class
            $classes = isset($vars['class']) ? [$vars['class']] : $index->getClasses();

            // Set the start point to the requested value, if there is only one class to index
            $group = ($start >= $group && count($classes) === 1) ? $start : $group;

            foreach ($classes as $class) {
                $this->indexClass($isGroup, $class, $index, $group);
            }
        }
        $this->getLogger()->info(
            sprintf('It took me %d seconds to do all the indexing%s', (time() - $startTime), PHP_EOL)
        );
        gc_collect_cycles(); // Garbage collection to prevent php from running out of memory

        return $groups;
    }

    /**
     * @param HTTPRequest $request
     * @return array
     */
    protected function taskSetup($request): array
    {
        $vars = $request->getVars();
        $this->debug = $this->debug || isset($vars['debug']);
        $group = $vars['group'] ?? 0;
        $start = $vars['start'] ?? 0;
        $isGroup = isset($vars['group']);

        return [$vars, $group, $start, $isGroup];
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        // Constructor should take care of this, but it's a good safeguard
        if (!$this->logger) {
            $this->setLogger(Injector::inst()->get(LoggerInterface::class));
        }

        return $this->logger;
    }

    /**
     * @param LoggerInterface|null $logger
     * @return SolrIndexTask
     */
    public function setLogger($logger = null): SolrIndexTask
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @param $isGroup
     * @param $class
     * @param BaseIndex $index
     * @param int $group
     * @throws Exception
     */
    private function indexClass($isGroup, $class, BaseIndex $index, int $group): void
    {
        $group = $group ?: 0;
        $this->getLogger()->info(sprintf('Indexing %s for %s', $class, $index->getIndexName()), []);

        $batchLength = DocumentFactory::config()->get('batchLength');
        $groups = (int)ceil($class::get()->count() / $batchLength);
        // Otherwise, run them all
        while ($group <= $groups) { // Run from oldest to newest
            try {
                $this->getLogger()->info(sprintf('Indexing group %s', $group));
                $group = $this->doReindex($group, $class, $batchLength, $index);
            } catch (RequestException $error) {
                $this->getLogger()->error($error->getResponse()->getBody()->getContents());
                $this->getLogger()->error(date('Y-m-d H:i:s') . PHP_EOL, []);
                $this->getLogger()->info(sprintf('Failure indexing at group %s', $group));
                $group++;
                continue;
            }
            // If it's a specific group to index, break after the first run
            if ($isGroup) {
                break;
            }
        }
    }

    /**
     * @param int $group
     * @param string $class
     * @param int $batchLength
     * @param BaseIndex $index
     * @return int
     * @throws Exception
     */
    private function doReindex($group, $class, $batchLength, BaseIndex $index): int
    {
        // Generate filtered list of local records
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        $filter = [];
        /** @var DataList|DataObject[] $items */
        $items = $baseClass::get()
            ->filter($filter)
            ->sort('ID ASC')
            ->limit($batchLength, ($group * $batchLength));
        $update = $index->getClient()->createUpdate();
        if ($items->count()) {
            $this->service->setInDebugMode($this->debug);
            $this->service->updateIndex($index, $items, $update);
        }
        $update->addCommit();
        $index->getClient()->update($update);
        $group++;

        return $group;
    }
}
