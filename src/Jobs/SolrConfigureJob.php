<?php
/**
 * class SolrConfigureJob|Firesphere\SolrSearch\Jobs\SolrConfigureJob Configure cores from the CMS
 *
 * @package Firesphere\Solr\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Jobs;

use Firesphere\SolrSearch\Tasks\SolrConfigureTask;
use Psr\SimpleCache\InvalidArgumentException;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationException;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Solarium\Exception\HttpException;

/**
 * Class SolrConfigureJob
 *
 * Generate, upload and activate a Solr Core through {@link SolrConfigureTask}
 *
 * @package Firesphere\Solr\Search
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
        return _t(__CLASS__ . '.TITLE', 'Configure new or re-configure existing Solr cores');
    }

    /**
     * Process the queue for indexes that need to be indexed properly
     *
     * @return void
     * @throws HTTPException
     * @throws ValidationException
     * @throws InvalidArgumentException
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
