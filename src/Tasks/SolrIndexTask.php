<?php
/**
 * Class SolrIndexTask|Firesphere\SolrSearch\Tasks\SolrIndexTask Index Solr cores
 *
 * @package Firesphere\SolrSearch\Tasks
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Tasks;

use Exception;
use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Helpers\SolrLogger;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\States\SiteState;
use Firesphere\SolrSearch\Traits\LoggerTrait;
use Firesphere\SolrSearch\Traits\SolrIndexTrait;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use ReflectionException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\SS_List;
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
    use SolrIndexTrait;
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
     * SolrIndexTask constructor. Sets up the document factory
     *
     * @throws ReflectionException
     */
    public function __construct()
    {
        parent::__construct();
        // Only index live items.
        // The old FTS module also indexed Draft items. This is unnecessary
        // If versioned is needed, a separate Versioned Search module is required
        Versioned::set_reading_mode(Versioned::DEFAULT_MODE);
        $this->setService(Injector::inst()->get(SolrCoreService::class));
        $this->setLogger(Injector::inst()->get(LoggerInterface::class));
        $this->setDebug(Director::isDev() || Director::is_cli());
        $this->setBatchLength(DocumentFactory::config()->get('batchLength'));
        $cores = SolrCoreService::config()->get('cpucores') ?: 1;
        $this->setCores($cores);
        $currentStates = SiteState::currentStates();
        SiteState::setDefaultStates($currentStates);
    }

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request Current request
     * @return int|bool
     * @throws Exception
     * @throws GuzzleException
     */
    public function run($request)
    {
        $start = time();
        $this->getLogger()->info(date('Y-m-d H:i:s'));
        list($vars, $group, $isGroup) = $this->taskSetup($request);
        $groups = 0;
        $indexes = $this->service->getValidIndexes($request->getVar('index'));

        foreach ($indexes as $indexName) {
            /** @var BaseIndex $index */
            $index = Injector::inst()->get($indexName, false);
            $this->setIndex($index);

            $indexClasses = $this->index->getClasses();
            $classes = $this->getClasses($vars, $indexClasses);
            if (!count($classes)) {
                continue;
            }

            $this->clearIndex($vars);

            $groups = $this->indexClassForIndex($classes, $isGroup, $group);
        }

        $this->getLogger()->info(gmdate('Y-m-d H:i:s'));
        $time = gmdate('H:i:s', (time() - $start));
        $this->getLogger()->info(sprintf('Time taken: %s', $time));

        return $groups;
    }

    /**
     * Set up the requirements for this task
     *
     * @param HTTPRequest $request Current request
     * @return array
     */
    protected function taskSetup($request): array
    {
        $vars = $request->getVars();
        $debug = $this->isDebug() || isset($vars['debug']);
        // Forcefully set the debugging to whatever the outcome of the above is
        $this->setDebug($debug, true);
        $group = $vars['group'] ?? 0;
        $start = $vars['start'] ?? 0;
        $group = ($start > $group) ? $start : $group;
        $isGroup = isset($vars['group']);

        return [$vars, $group, $isGroup];
    }

    /**
     * get the classes to run for this task execution
     *
     * @param array $vars URL GET Parameters
     * @param array $classes Classes to index
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
     * @param array $vars URL GET Parameters
     * @throws Exception
     */
    public function clearIndex($vars)
    {
        if (!empty($vars['clear'])) {
            $this->getLogger()->info(sprintf('Clearing index %s', $this->index->getIndexName()));
            $this->service->doManipulate(ArrayList::create([]), SolrCoreService::DELETE_TYPE_ALL, $this->index);
        }
    }

    /**
     * Index the classes for a specific index
     *
     * @param array $classes Classes that need indexing
     * @param bool $isGroup Indexing a specific group?
     * @param int $group Group to index
     * @return int
     * @throws Exception
     * @throws GuzzleException
     */
    protected function indexClassForIndex($classes, $isGroup, $group): int
    {
        $groups = 0;
        foreach ($classes as $class) {
            $groups = $this->indexClass($isGroup, $class, $group);
        }

        return $groups;
    }

    /**
     * Index a single class for a given index. {@link static::indexClassForIndex()}
     *
     * @param bool $isGroup Is a specific group indexed
     * @param string $class Class to index
     * @param int $group Group to index
     * @return int
     * @throws GuzzleException
     * @throws ValidationException
     */
    private function indexClass($isGroup, $class, int $group): int
    {
        $this->getLogger()->info(sprintf('Indexing %s for %s', $class, $this->getIndex()->getIndexName()));
        list($totalGroups, $groups) = $this->getGroupSettings($isGroup, $class, $group);
        $this->getLogger()->info(sprintf('Total groups %s', $totalGroups));
        do { // Run from oldest to newest
            try {
                if ($this->hasPCNTL()) {
                    // @codeCoverageIgnoreStart
                    $group = $this->spawnChildren($class, $group, $groups);
                // @codeCoverageIgnoreEnd
                } else {
                    $this->doReindex($group, $class);
                }
                $group++;
            } catch (Exception $error) {
                // @codeCoverageIgnoreStart
                $this->logException($this->index->getIndexName(), $group, $error);
                $group++;
                continue;
                // @codeCoverageIgnoreEnd
            }
        } while ($group <= $groups);

        return $totalGroups;
    }

    /**
     * Check the amount of groups and the total against the isGroup check.
     *
     * @param bool $isGroup Is it a specific group
     * @param string $class Class to check
     * @param int $group Current group to index
     * @return array
     */
    private function getGroupSettings($isGroup, $class, int $group): array
    {
        $totalGroups = (int)ceil($class::get()->count() / $this->getBatchLength());
        $groups = $isGroup ? ($group + $this->getCores() - 1) : $totalGroups;

        return [$totalGroups, $groups];
    }

    /**
     * Check if PCNTL is available and/or useable.
     * The unittest param is from phpunit.xml.dist, meant to bypass the exit(0) call
     * The pcntl parameter check is for unit tests, but PHPUnit does not support PCNTL (yet)
     *
     * @return bool
     */
    private function hasPCNTL()
    {
        return Director::is_cli() &&
            function_exists('pcntl_fork') &&
            (Controller::curr()->getRequest()->getVar('unittest') === 'pcntl' ||
                !Controller::curr()->getRequest()->getVar('unittest'));
    }

    /**
     * For each core, spawn a child process that will handle a separate group.
     * This speeds up indexing through CLI massively.
     *
     * @codeCoverageIgnore Can't be tested because PCNTL is not available
     * @param string $class Class to index
     * @param int $group Group to index
     * @param int $groups Total amount of groups
     * @return int Last group indexed
     * @throws Exception
     * @throws GuzzleException
     */
    private function spawnChildren($class, int $group, int $groups): int
    {
        $start = $group;
        $pids = [];
        $cores = $this->getCores();
        // for each core, start a grouped indexing
        for ($i = 0; $i < $cores; $i++) {
            $start = $group + $i;
            if ($start < $groups) {
                $this->runForkedChild($class, $pids, $start);
            }
        }
        // Wait for each child to finish
        // It needs to wait for them independently,
        // or it runs out of memory for some reason
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        $commit = $this->index->getClient()->createUpdate();
        $commit->addCommit();

        $this->index->getClient()->update($commit);

        return $start;
    }

    /**
     * Create a fork and run the child
     *
     * @codeCoverageIgnore Can't be tested because PCNTL is not available
     * @param string $class Class to index
     * @param array $pids Array of all the child Process IDs
     * @param int $start Start point for the objects
     * @return void
     * @throws GuzzleException
     * @throws ValidationException
     */
    private function runForkedChild($class, array &$pids, int $start): void
    {
        $pid = pcntl_fork();
        // PID needs to be pushed before anything else, for some reason
        $pids[] = $pid;
        $config = DB::getConfig();
        DB::connect($config);
        try {
            $this->runChild($class, $pid, $start);
        } catch (Exception $e) {
            exit(0);
        }
    }

    /**
     * Ren a single child index operation
     *
     * @codeCoverageIgnore Can't be tested because PCNTL is not available
     * @param string $class Class to index
     * @param int $pid PID of the child
     * @param int $start Position to start
     * @throws GuzzleException
     * @throws ValidationException
     * @throws Exception
     */
    private function runChild($class, int $pid, int $start): void
    {
        if ($pid === 0) {
            try {
                $this->doReindex($start, $class, $pid);
            } catch (Exception $error) {
                SolrLogger::logMessage('ERROR', $error, $this->index->getIndexName());
                $msg = sprintf(
                    'Something went wrong while indexing %s on %s, see the logs for details',
                    $start,
                    $this->index->getIndexName()
                );
                throw new Exception($msg);
            }
        }
    }

    /**
     * Reindex the given group, for each state
     *
     * @param int $group Group to index
     * @param string $class Class to index
     * @param bool|int $pid Are we a child process or not
     * @throws Exception
     */
    private function doReindex($group, $class, $pid = false)
    {
        $start = time();
        foreach (SiteState::getStates() as $state) {
            if ($state !== 'default' && !empty($state)) {
                SiteState::withState($state);
            }
            $this->indexStateClass($group, $class);
        }

        SiteState::withState(SiteState::DEFAULT_STATE);
        $end = gmdate('i:s', time() - $start);
        $this->getLogger()->info(sprintf('Indexed group %s in %s', $group, $end));

        // @codeCoverageIgnoreStart
        if ($pid !== false) {
            exit(0);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Index a group of a class for a specific state and index
     *
     * @param string $group Group to index
     * @param string $class Class to index
     * @throws Exception
     */
    private function indexStateClass($group, $class): void
    {
        // Generate filtered list of local records
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        /** @var DataList|DataObject[] $items */
        $items = DataObject::get($baseClass)
            ->sort('ID ASC')
            ->limit($this->getBatchLength(), ($group * $this->getBatchLength()));
        $this->updateIndex($items);
    }

    /**
     * Execute the update on the client
     *
     * @param SS_List $items Items to index
     * @throws Exception
     */
    private function updateIndex($items): void
    {
        $index = $this->getIndex();
        $client = $index->getClient();
        $update = $client->createUpdate();
        $this->service->setDebug($this->debug);
        $this->service->updateIndex($index, $items, $update);
        $client->update($update);
    }

    /**
     * Log an exception if it happens. Most are catched, these logs are for the developers
     * to identify problems and fix them.
     *
     * @codeCoverageIgnore This is actually tested through reflection
     * @param string $index Index that is currently running
     * @param int $group Group currently attempted to index
     * @param Exception $exception Exception that's been thrown
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
