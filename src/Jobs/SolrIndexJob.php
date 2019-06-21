<?php


namespace Firesphere\SolrSearch\Jobs;

use Exception;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Tasks\SolrIndexTask;
use ReflectionClass;
use ReflectionException;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Class SolrIndexJob
 * @package Firesphere\SolrSearch\Jobs
 *
 * @todo fix the per-class index things
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
     * @var array
     */
    protected $indexes;

    /**
     * SolrIndexJob constructor.
     * @param array $params
     * @throws ReflectionException
     */
    public function __construct($params = [])
    {
        parent::__construct($params);
        // Make sure indexes are set on first run, but not again after that :)
        if (!$this->indexes || !count($this->indexes)) { // If indexes are set, don't load them.
            $indexes = ClassInfo::subclassesFor(BaseIndex::class);

            foreach ($indexes as $index) {
                // Skip the abstract base
                $ref = new ReflectionClass($index);
                if (!$ref->isInstantiable()) {
                    continue;
                }
                $this->indexes[] = $index;
            }
        }
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Index groups to Solr search';
    }

    /**
     * Do some processing yourself!
     * @throws Exception
     */
    public function process()
    {
        $this->currentStep = $this->currentStep ?: 0;
        $index = Injector::inst()->get($this->indexes[0]);
        $this->classToIndex = $this->classToIndex ?: $index->getClasses();
        $indexArgs = [
            'group' => $this->currentStep,
            'index' => $this->indexes[0],
            'class' => $this->classToIndex[0]
        ];
        /** @var BaseIndex $index */
        /** @var SolrIndexTask $task */
        $task = Injector::inst()->get(SolrIndexTask::class);
        $request = new HTTPRequest(
            'GET',
            '/dev/tasks/SolrIndexTask',
            $indexArgs
        );

        $result = $task->run($request);
        if ($result !== false) {
            $this->totalSteps = $result;
            // If the result is false, the job should fail too
            // Thus, only set to true if the result isn't false :)
            $this->isComplete = true;
        }
    }

    /**
     * @throws ReflectionException
     */
    public function afterComplete()
    {
        // No more steps to execute on this class, let's go to the next class
        if ($this->currentStep >= $this->totalSteps) {
            array_shift($this->classToIndex);
        }
        // If there are no classes left in this index, go to the next index
        if (!count($this->classToIndex)) {
            array_shift($this->indexes);
        }
        // No indexes left to run, let's call it a day
        if (!count($this->indexes)) {
            parent::afterComplete();
        } else {
            $nextJob = new self();
            $nextJob->currentStep = $this->currentStep + 1;
            // Make sure the job doesn't stop if the current step accidentally is bigger than the total
            $nextJob->totalSteps = $this->totalSteps ?: $this->currentStep + 1;
            $nextJob->setClassToIndex($this->classToIndex);
            $nextJob->setIndexes($this->indexes);

            // Add a wee break to let the system recover from this heavy operation
            Injector::inst()->get(QueuedJobService::class)
                ->queueJob($nextJob, date('Y-m-d H:i:00', strtotime('+1 minutes')));
        }
    }

    /**
     * @param array $classToIndex
     * @return SolrIndexJob
     */
    public function setClassToIndex($classToIndex)
    {
        $this->classToIndex = $classToIndex;

        return $this;
    }

    /**
     * @param array $indexes
     * @return SolrIndexJob
     */
    public function setIndexes($indexes)
    {
        $this->indexes = $indexes;

        return $this;
    }
}
