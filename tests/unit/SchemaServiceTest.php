<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SchemaService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class SchemaServiceTest extends SapphireTest
{

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

        return parent::setUp();
    }
}
