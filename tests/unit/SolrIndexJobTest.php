<?php


namespace Firesphere\SolrSearch\Tests;


use Firesphere\SolrSearch\Jobs\SolrConfigureJob;
use Firesphere\SolrSearch\Jobs\SolrIndexJob;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class SolrIndexJobTest extends SapphireTest
{
    /**
     * @var SolrConfigureJob
     */
    protected $job;

    /**
     * @var SolrIndexJob
     */
    protected $indexJob;

    protected function setUp()
    {
        $this->job = Injector::inst()->get(SolrConfigureJob::class);
        $this->indexJob = Injector::inst()->get(SolrIndexJob::class);
        return parent::setUp();
    }

    public function testGetTitle()
    {
        $this->assertEquals('Index groups to Solr search', $this->indexJob->getTitle());
    }

    public function testProcess()
    {
        $this->job->process();
        $this->indexJob->setIndexes([\CircleCITestIndex::class]);
        $this->indexJob->process();

        $this->assertEquals(1, $this->indexJob->totalSteps);
    }
}