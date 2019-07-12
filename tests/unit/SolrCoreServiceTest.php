<?php


namespace Firesphere\SolrSearch\Tests;

use CircleCITestIndex;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Tasks\SolrConfigureTask;
use Firesphere\SolrSearch\Tasks\SolrIndexTask;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;

class SolrCoreServiceTest extends SapphireTest
{
    /**
     * @var SolrCoreService
     */
    protected $service;

    public function testIndexes()
    {
        $service = new SolrCoreService();

        $expected = [
            CircleCITestIndex::class,
            TestIndex::class,
            TestIndexTwo::class,
        ];

        $this->assertEquals($expected, $service->getValidIndexes());
        $this->assertEquals([\CircleCITestIndex::class], $service->getValidIndexes(\CircleCITestIndex::class));
    }


    /**
     * @expectedException \LogicException
     */
    public function testUpdateItemsFail()
    {
        $this->service->updateItems(null, SolrCoreService::CREATE_TYPE);
    }

    /**
     * @expectedException \LogicException
     */
    public function testUpdateItemsFailWrongCall()
    {
        $this->service->updateItems(['test'], SolrCoreService::DELETE_TYPE_ALL);
    }

    public function testUpdateItems()
    {
        $index = new CircleCITestIndex();
        $query = new BaseQuery();
        $query->addTerm('*:*');
        $items = SiteTree::get();

        $result = $this->service->updateItems($items, SolrCoreService::UPDATE_TYPE, CircleCITestIndex::class);
        $this->assertEquals(200, $result->getResponse()->getStatusCode());

        $this->service->updateItems($items, SolrCoreService::DELETE_TYPE, CircleCITestIndex::class);
        $this->assertEquals(0, $index->doSearch($query)->getTotalItems());
    }

    public function testUpdateItemsEmptyArray()
    {
        $index = new CircleCITestIndex();
        $query = new BaseQuery();
        $this->service->doManipulate(ArrayList::create(), SolrCoreService::DELETE_TYPE_ALL, $index);
        $this->assertEquals(0, $index->doSearch($query)->getTotalItems());
    }

    /**
     * @expectedException \LogicException
     */
    public function testWrongUpdateItems()
    {
        $items = SiteTree::get();

        $this->service->updateItems($items, SolrCoreService::UPDATE_TYPE, 'NonExisting');
    }

    public function testCoreStatus()
    {
        $status = $this->service->coreStatus(CircleCITestIndex::class);
        $deprecatedStatus = $this->service->coreIsActive(CircleCITestIndex::class);
        $this->assertEquals($status->getVersion(), $deprecatedStatus->getVersion());
        $this->assertEquals($status->getNumberOfDocuments(), $deprecatedStatus->getNumberOfDocuments());
        $this->assertEquals($status->getCoreName(), $deprecatedStatus->getCoreName());

        $this->assertEquals('CircleCITestIndex', $status->getCoreName());
    }

    public function testCoreUnload()
    {
        $status1 = $this->service->coreStatus(CircleCITestIndex::class);
        $this->service->coreUnload(CircleCITestIndex::class);
        $status2 = $this->service->coreStatus(CircleCITestIndex::class);
        $this->assertEquals(0, $status2->getUptime());
        $this->assertNotEquals($status1->getUptime(), $status2->getUptime());
    }

    protected function setUp()
    {
        $this->service = new SolrCoreService();
        return parent::setUp();
    }

    protected function tearDown()
    {
        /** @var SolrConfigureTask $task */
        $task = Injector::inst()->get(SolrConfigureTask::class);
        $task->run(new NullHTTPRequest());
        /** @var SolrIndexTask $task */
        $task = Injector::inst()->get(SolrIndexTask::class);
        $task->run(new NullHTTPRequest());
        parent::tearDown();
    }
}
