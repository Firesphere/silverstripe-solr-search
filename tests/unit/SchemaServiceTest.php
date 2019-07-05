<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SchemaService;
use Page;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class SchemaServiceTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

    /**
     * @var SchemaService
     */
    protected $service;

    public function testGetSetIndex()
    {
        $index = new TestIndex();

        $this->assertNull($this->service->getIndex());
        $this->service->setIndex($index);
        $this->assertInstanceOf(BaseIndex::class, $this->service->getIndex());
    }

    public function testGettersSetters()
    {
        $index = new TestIndex();

        $this->service->setIndex($index);
        $this->assertEquals($index->getIndexName(), $this->service->getIndexName());

        $this->assertEquals($index->getDefaultField(), $this->service->getDefaultField());
    }

    protected function setUp()
    {
        $this->service = Injector::inst()->get(SchemaService::class);
        Injector::inst()->get(Page::class)->requireDefaultRecords();
        foreach (self::$extra_dataobjects as $className) {
            Config::modify()->merge($className, 'extensions', [DataObjectExtension::class]);
        }

        parent::setUp();
    }
}
