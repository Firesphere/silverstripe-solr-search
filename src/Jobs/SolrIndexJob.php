<?php


namespace Firesphere\SolrSearch\Jobs;

use Firesphere\SolrSearch\Tasks\SolrIndexTask;
use SilverStripe\Control\HTTPRequest;
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
    protected $classToIndex;

    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Index groups to Solr search';
    }

    /**
     * Do some processing yourself!
     * @throws \Exception
     */
    public function process()
    {
        $this->currentStep = $this->currentStep ?: 0;
        /** @var SolrIndexTask $task */
        $task = Injector::inst()->get(SolrIndexTask::class);
        $request = new HTTPRequest(
            'GET',
            '/dev/tasks/SolrIndexTask',
            ['group' => $this->currentStep]
        );
        $this->totalSteps = $task->run($request);

        $this->isComplete = true;
    }

    public function afterComplete()
    {
        if ($this->currentStep <= $this->totalSteps) {
            $nextJob = new self();
            $nextJob->currentStep = $this->currentStep + 1;
            $nextJob->totalSteps = $this->totalSteps;

            // Add a wee break to let the system recover from this heavy operation
            Injector::inst()->get(QueuedJobService::class)
                ->queueJob($nextJob, date('Y-m-d H:i:00', strtotime('+1 minutes')));
        }
        parent::afterComplete();
    }
}
