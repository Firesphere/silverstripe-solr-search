<?php


namespace Firesphere\SolrSearch\Jobs;

use Firesphere\SolrSearch\Tasks\ClearDirtyClassesTask;
use SilverStripe\Control\NullHTTPRequest;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

class SolrClearDirtyClassesJob extends AbstractQueuedJob
{

    /**
     * @return string
     */
    public function getTitle()
    {
        return _t(self::class . '.CLEARSOLRDIRTY', 'Clear out dirty objects in Solr');
    }

    /**
     * Do some processing yourself!
     */
    public function process()
    {
        $request = new NullHTTPRequest();
        $task = new ClearDirtyClassesTask();
        $task->run($request);

        $this->isComplete = true;
    }
}
