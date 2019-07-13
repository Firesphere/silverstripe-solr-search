<?php


namespace Firesphere\SolrSearch\Jobs;

use Exception;
use Firesphere\SolrSearch\Tasks\SolrConfigureTask;
use GuzzleHttp\Exception\RequestException;
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
     * @return void
     * @throws ReflectionException
     */
    public function process()
    {
        /** @var SolrConfigureTask $task */
        $task = Injector::inst()->get(SolrConfigureTask::class);
        /** @var bool|Exception $result */
        try {
            $task->run(new NullHTTPRequest());
        } catch (RequestException $error) {
            $this->addMessage($error->getResponse()->getBody()->getContents());
        }

        // Mark as complete if everything is fine
        $this->isComplete = true;
    }
}
