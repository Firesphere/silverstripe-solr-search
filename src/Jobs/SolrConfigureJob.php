<?php


namespace Firesphere\SolrSearch\Jobs;

use Firesphere\SolrSearch\Tasks\SolrConfigureTask;
use GuzzleHttp\Exception\GuzzleException;
use ReflectionException;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationException;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * Class SolrConfigureJob
 *
 * Generate, upload and activate a Solr Core through {@link SolrConfigureTask}
 *
 * @package Firesphere\SolrSearch\Jobs
 */
class SolrConfigureJob extends AbstractQueuedJob
{

    /**
     * My name
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'Configure new or re-configure existing Solr cores';
    }

    /**
     * Process the queue for indexes that need to be indexed properly
     *
     * @return void
     * @throws ReflectionException
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function process()
    {
        /** @var SolrConfigureTask $task */
        $task = Injector::inst()->get(SolrConfigureTask::class);
        $task->run(new NullHTTPRequest());
        // Mark as complete if everything is fine
        $this->isComplete = true;
    }
}
