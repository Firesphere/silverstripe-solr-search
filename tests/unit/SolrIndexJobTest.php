<?php


namespace Firesphere\SolrSearch\Tests;

use CircleCITestIndex;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Jobs\SolrConfigureJob;
use Firesphere\SolrSearch\Jobs\SolrIndexJob;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use stdClass;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

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

    public function testGetTitle()
    {
        $this->assertEquals('Index groups to Solr search', $this->indexJob->getTitle());
    }

    public function testProcess()
    {
        $this->job->process();
        $result = $this->indexJob->process();

        $this->assertNotContains(BaseIndex::class, $this->job->getIndexes());
        $this->assertEquals(0, $result->totalSteps);

        $job = new SolrIndexJob();
        $data = new stdClass();

        $data->indexes = [CircleCITestIndex::class];
        $data->classToIndex = [];

        $job->setJobData(0, 0, false, $data, []);

        $job->process();

        $this->assertCount(1, $job->getIndexes());
        $this->assertCount(1, $job->getClassToIndex());
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

    public function testGettersSetters()
    {
        $this->indexJob->setIndexes([CircleCITestIndex::class]);
        $this->assertEquals([CircleCITestIndex::class], $this->indexJob->getIndexes());
        $this->indexJob->setClassToIndex([SiteTree::class]);
        $this->assertEquals([SiteTree::class], $this->indexJob->getClassToIndex());
    }

    protected function setUp()
    {
        $this->job = Injector::inst()->get(SolrConfigureJob::class);
        $this->indexJob = Injector::inst()->get(SolrIndexJob::class);

        return parent::setUp();
    }
}
