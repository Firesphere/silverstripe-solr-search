<?php
/**
 * class SolrIndexJob|Firesphere\SolrSearch\Jobs\SolrIndexJob Index items from the CMS through a QueuedJob
 *
 * @package Firesphere\Solr\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Jobs;

use Exception;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Tasks\SolrIndexTask;
use GuzzleHttp\Exception\GuzzleException;
use ReflectionException;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use stdClass;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Class SolrIndexJob is a queued job to index all existing indexes and their classes.
 *
 * It always runs on all indexes, to make sure all indexes are up to date.
 *
 * @package Firesphere\Solr\Search
 */
class SolrIndexJob extends AbstractQueuedJob
{

    /**
     * The class that should be indexed.
     * If set, the task should run the given class with the given group
     * The class should be popped off the array at the end of each subset
     * so the next class becomes the class to index.
     *
     * Rinse and repeat for each class in the index, until this array is empty
     *
     * @var array
     */
    protected $classToIndex = [];
    /**
     * The indexes that need to run.
     *
     * @var array
     */
    protected $indexes;

    /**
     * My name
     *
     * @return string
     */
    public function getTitle()
    {
        return 'Index groups to Solr search';
    }

    /**
     * Process this job
     *
     * @return self
     * @throws Exception
     * @throws GuzzleException
     */
    public function process()
    {
        $data = $this->jobData;

        $this->configureRun($data);

        $this->currentStep = $this->currentStep ?: 0;
        $index = Injector::inst()->get($this->indexes[0]);
        $this->classToIndex = count($this->classToIndex) ? $this->classToIndex : $index->getClasses();
        $indexArgs = [
            'group' => $this->currentStep,
            'index' => $this->indexes[0],
            'class' => $this->classToIndex[0],
        ];
        /** @var SolrIndexTask $task */
        $task = Injector::inst()->get(SolrIndexTask::class);
        $request = new HTTPRequest(
            'GET',
            '/dev/tasks/SolrIndexTask',
            $indexArgs
        );

        $result = $task->run($request);
        $this->totalSteps = $result;
        // If the result is false, the job should fail too
        // Thus, only set to true if the result isn't false :)
        $this->isComplete = true;

        /** @var self $this */
        return $this;
    }

    /**
     * Configure the run for the valid indexes
     *
     * @param stdClass|null $data
     * @throws ReflectionException
     */
    protected function configureRun($data)
    {
        // If null gets passed in, it goes a bit wonky with the check for indexes
        if (!$data) {
            $data = new stdClass();
            $data->indexes = null;
        }
        if (!isset($data->indexes) || !count($data->indexes)) { // If indexes are set, don't load them.
            $this->indexes = (new SolrCoreService())->getValidIndexes();
        } else {
            $this->setIndexes($data->indexes);
            $this->setClassToIndex($data->classToIndex);
        }
    }

    /**
     * Set up the next job if needed
     */
    public function afterComplete()
    {
        [$currentStep, $totalSteps] = $this->getNextSteps();
        // If there are no indexes left to run, let's call it a day
        if (count($this->indexes)) {
            $nextJob = new self();
            $jobData = new stdClass();

            $jobData->classToIndex = $this->getClassToIndex();
            $jobData->indexes = $this->getIndexes();
            $nextJob->setJobData($totalSteps, $currentStep, false, $jobData, []);

            // Add a wee break to let the system recover from this heavy operation
            Injector::inst()->get(QueuedJobService::class)
                ->queueJob($nextJob, date('Y-m-d H:i:00', strtotime('+1 minutes')));
        }
        parent::afterComplete();
    }

    /**
     * Get the next step to execute
     *
     * @return array
     */
    protected function getNextSteps(): array
    {
        $cores = SolrCoreService::config()->get('cpucores') ?: 1;
        // Force a single count for when the job is not run from CLI
        if (!Director::is_cli()) {
            $cores = 1;
        }
        $currentStep = $this->currentStep + $cores; // Add the amount of cores
        $totalSteps = $this->totalSteps;
        // No more steps to execute on this class, let's go to the next class
        if ($currentStep >= $totalSteps) {
            array_shift($this->classToIndex);
            // Reset the current step, a complete new set of data is coming
            $currentStep = 0;
            $totalSteps = 1;
        }
        // If there are no classes left in this index, go to the next index
        if (!count($this->classToIndex)) {
            array_shift($this->indexes);
            // Reset the current step, a complete new set of data is coming
            $currentStep = 0;
            $totalSteps = 1;
        }

        return [$currentStep, $totalSteps];
    }

    /**
     * Which Indexes should I index
     *
     * @return array
     */
    public function getClassToIndex(): array
    {
        return $this->classToIndex;
    }

    /**
     * Which classes should I index
     *
     * @param array $classToIndex
     * @return SolrIndexJob
     */
    public function setClassToIndex($classToIndex)
    {
        $this->classToIndex = $classToIndex;

        return $this;
    }

    /**
     * Get the indexes
     *
     * @return array
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * Set the indexes if needed
     *
     * @param array $indexes
     * @return SolrIndexJob
     */
    public function setIndexes($indexes)
    {
        $this->indexes = $indexes;

        return $this;
    }
}
