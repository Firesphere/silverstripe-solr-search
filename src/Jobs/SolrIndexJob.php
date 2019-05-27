<?php


namespace Firesphere\SolrSearch\Jobs;


use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

class SolrIndexJob extends AbstractQueuedJob
{

    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Index groups to Solr search';
    }

    /**
     * Do some processing yourself!
     */
    public function process()
    {
        // TODO: Implement process() method.
    }
}