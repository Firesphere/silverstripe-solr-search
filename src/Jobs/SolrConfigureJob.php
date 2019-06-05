<?php


namespace Firesphere\SolrSearch\Jobs;

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
     * @throws ReflectionException
     * @return false|null
     */
    public function process()
    {
        /** @var SolrConfigureTask $task */
        $task = Injector::inst()->get(SolrConfigureTask::class);
        $result = $task->run(new NullHTTPRequest());

        if ($result !== true) {
            return false;
        }

        $this->isComplete = true;
    }
}
