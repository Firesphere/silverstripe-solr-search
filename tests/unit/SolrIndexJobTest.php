<?php


namespace Firesphere\SolrSearch\Tests;

use CircleCITestIndex;
use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Jobs\SolrConfigureJob;
use Firesphere\SolrSearch\Jobs\SolrIndexJob;
use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use stdClass;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

class SolrIndexJobTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

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

        $this->assertNotContains(BaseIndex::class, $this->indexJob->getIndexes(), 'Test index removed');
        $this->assertEquals(0, $result->totalSteps, 'Test reset total count');

        $job = new SolrIndexJob();
        $data = new stdClass();

        $data->indexes = [CircleCITestIndex::class];
        $data->classToIndex = [];

        $job->setJobData(0, 0, false, $data, []);

        $job->process();

        $this->assertCount(1, $job->getIndexes(), 'Test index count');
        $this->assertCount(1, $job->getClassToIndex(), 'Test class count');
    }

    public function testAfterComplete()
    {
        $job = new SolrIndexJob();
        $job->setIndexes(['Index1', 'Index2']);
        $job->setClassToIndex(['Test']);
        $job->afterComplete();

        $newJob = QueuedJobDescriptor::get()->filter(['Implementation' => SolrIndexJob::class])->first();
        $jobData = unserialize($newJob->SavedJobData);
        $this->assertCount(1, $jobData->indexes, 'Should have one index after complete');
        $this->assertCount(0, $jobData->classToIndex, 'Set to default count as the index is shifted');
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
        parent::setUp();
        Injector::inst()->get(Page::class)->requireDefaultRecords();
        foreach (self::$extra_dataobjects as $className) {
            Config::modify()->merge($className, 'extensions', [DataObjectExtension::class]);
        }
        $this->indexJob = Injector::inst()->get(SolrIndexJob::class);
        $this->job = Injector::inst()->get(SolrConfigureJob::class);
    }
}
