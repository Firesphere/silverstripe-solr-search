<?php


namespace Firesphere\SolrSearch\Jobs;

use Firesphere\SolrSearch\Tasks\ClearDirtyClassesTask;
use GuzzleHttp\Exception\GuzzleException;
use ReflectionException;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\ORM\ValidationException;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * Class ClearDirtyClassesJob is the queued job version of the ClearDirtyClassesTask
 *
 * Clean up any dirty classes in Solr due to objects not being updated properly
 *
 * @package Firesphere\SolrSearch\Jobs
 */
class ClearDirtyClassesJob extends AbstractQueuedJob
{

    /**
     * Give this puppy a name
     *
     * @return string Title of this job
     */
    public function getTitle()
    {
        return _t(self::class . '.CLEARSOLRDIRTY', 'Clear out dirty objects in Solr');
    }

    /**
     * Run the dirty class cleanup task from Queued Jobs
     *
     * @throws GuzzleException
     * @throws ValidationException
     * @throws ReflectionException
     */
    public function process()
    {
        $request = new NullHTTPRequest();
        $task = new ClearDirtyClassesTask();
        $task->run($request);

        $this->isComplete = true;
    }
}
