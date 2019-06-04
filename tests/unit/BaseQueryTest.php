<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Queries\BaseQuery;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class BaseQueryTest extends SapphireTest
{
    /**
     * @var BaseQuery
     */
    protected $query;

    protected function setUp()
    {
        $this->query = Injector::inst()->get(BaseQuery::class);
        parent::setUp();
    }

    public function testClass()
    {
        $this->assertEquals(0, $this->query->getStart());
        $this->query->setStart(1);
        $this->assertEquals(1, $this->query->getStart());
        $this->assertEquals(10, $this->query->getRows());
        $this->query->setRows(20);
        $this->assertEquals(20, $this->query->getRows());
        $this->assertEmpty($this->query->getClasses());
        $this->query->addClass('test');
        $this->assertCount(1, $this->query->getClasses());
        $this->query->setClasses([]);
        $this->assertCount(0, $this->query->getClasses());
        $this->assertEmpty($this->query->getExclude());
        $this->query->setExclude(['test']);
        $this->assertEquals(['test'], $this->query->getExclude());
        $this->query->addExclude('test', 'test');
        $this->assertEquals(['test', 'test' => 'test'], $this->query->getExclude());
        $this->query->addField('test', 'test');
        $this->assertEquals(['test' => 'test'], $this->query->getFields());
        $this->assertEquals(0, $this->query->getFacetsMinCount());
        $this->query->setFacetsMinCount(15);
        $this->assertEquals(15, $this->query->getFacetsMinCount());
    }
}
