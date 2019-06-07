<?php


namespace Firesphere\SolrSearch\Jobs;

use Exception;
use Firesphere\SolrSearch\Tasks\SolrConfigureTask;
use ReflectionException;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

class SolrConfigureJob extends AbstractQueuedJob
{

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return 'Configure new or re-configure existing Solr cores';
    }

    /**
     * Do some processing yourself!
     * @return false|null
     * @throws ReflectionException
     */
    public function process()
    {
        /** @var SolrConfigureTask $task */
        $task = Injector::inst()->get(SolrConfigureTask::class);
        /** @var bool|Exception $result */
        $result = $task->run(new NullHTTPRequest());

        // If there's an exception, return the result
        if ($result !== true) {
            return $result;
        }

        // Mark as complete if everything is fine
        $this->isComplete = true;
    }
}
