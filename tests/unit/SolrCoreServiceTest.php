<?php


namespace Firesphere\SolrSearch\Tests;

use CircleCITestIndex;
use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Tasks\SolrConfigureTask;
use Firesphere\SolrSearch\Tasks\SolrIndexTask;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Page;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use Solarium\Core\Client\Client;

class SolrCoreServiceTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

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
            TestIndex3::class,
            TestIndexTwo::class,
        ];

        $this->assertEquals($expected, $service->getValidIndexes());
        $this->assertEquals([CircleCITestIndex::class], $service->getValidIndexes(CircleCITestIndex::class));
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

    public function testGetSetClient()
    {
        $client = $this->service->getClient();
        $this->assertInstanceOf(Client::class, $client);
        $service = $this->service->setClient($client);
        $this->assertInstanceOf(SolrCoreService::class, $service);
    }

    public function testGetSolrVersion()
    {
        if ($this->service->getSolrVersion() !== 5) {
            $this->assertEquals(4, $this->service->getSolrVersion());
        } else {
            $this->assertEquals(5, $this->service->getSolrVersion());
        }
        $version4 = [
            'responseHeader' =>
                [
                    'status' => 0,
                    'QTime'  => 10,
                ],
            'mode'           => 'std',
            'solr_home'      => '/var/solr/data',
            'lucene'         =>
                [
                    'solr-spec-version' => '4.3.2',
                ],
        ];
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], json_encode($version4)),
        ]);
        $handler = HandlerStack::create($mock);

        $this->assertEquals(4, $this->service->getSolrVersion($handler));
    }

    public function testValidClasses()
    {
        $this->assertFalse($this->service->isValidClass(ModelAdmin::class));
        $this->assertTrue($this->service->isValidClass(SiteTree::class));
        $this->assertTrue($this->service->isValidClass(Page::class));
        Debug::dump($this->service->getValidClasses());
    }

    protected function setUp()
    {
        parent::setUp();
        Injector::inst()->get(Page::class)->requireDefaultRecords();
        foreach (self::$extra_dataobjects as $className) {
            Config::modify()->merge($className, 'extensions', [DataObjectExtension::class]);
        }
        $this->service = new SolrCoreService();
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
