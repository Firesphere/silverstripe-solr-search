<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Jobs\SolrConfigureJob;
use Firesphere\SolrSearch\Jobs\SolrIndexJob;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;

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
        $result = $this->indexJob->process();

        $this->assertEquals(0, $result->totalSteps);

        $job = new SolrIndexJob();
        $job->jobData = new \stdClass();

        $job->jobData->indexes = [\CircleCITestIndex::class];
        $job->jobData->classToIndex = [];

        $job->process();

//        $this->assertEquals(1, $job->totalSteps);
    }

    public function testAfterComplete()
    {
        $job = new SolrIndexJob();
        $job->setIndexes(['Index1', 'Index2']);
        $job->setClassToIndex(['Test']);
        $job->afterComplete();

        $newJob = QueuedJobDescriptor::get()->filter(['Implementation' => SolrIndexJob::class])->first();
        $jobData = unserialize($newJob->SavedJobData);
        $this->assertCount(1, $jobData->indexes); // Set to default count as the index is shifted
        $this->assertCount(0, $jobData->classToIndex); // Set to default count as the index is shifted
    }
}
