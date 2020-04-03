<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Page;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class BaseQueryTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

    /**
     * @var BaseQuery
     */
    protected $query;

    public function testGetSet()
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
        $this->query->addField('test');
        $this->assertEquals(['test'], $this->query->getFields());
        $this->assertEquals(0, $this->query->getFacetsMinCount());
        $this->query->setFacetsMinCount(15);
        $this->assertEquals(15, $this->query->getFacetsMinCount());
        $this->query->setFields(['Field1', 'Field2']);
        $this->assertCount(2, $this->query->getFields());
        $this->query->setSort(['Field1']);
        $this->assertCount(1, $this->query->getSort());
        $this->query->setTerms(['Term' => 'Test']);
        $this->assertCount(1, $this->query->getTerms());
        $this->query->addTerm('String', ['Field1'], 2);
        $this->assertCount(2, $this->query->getTerms());
        $this->query->addFilter('Field1', 'test');
        $this->assertCount(1, $this->query->getFilter());
        $this->query->setFields([['Field1' => 'testing']]);
        $this->assertCount(1, $this->query->getFilter());
        $this->query->setFilter([['Field1' => 'Test']]);
        $this->assertCount(1, $this->query->getFilter());
        $this->query->setSpellcheck(false);
        $this->assertFalse($this->query->hasSpellcheck());
        $this->query->addBoostedField('Field1', 2);
        $this->assertEquals(2, $this->query->getBoostedFields()['Field1']);
        $this->query->setBoostedFields(['Field' => 2]);
        $this->assertEquals(2, $this->query->getBoostedFields()['Field']);
        $this->query->setHighlight(['test']);
        $this->assertEquals(['test'], $this->query->getHighlight());
        $this->query->addHighlight('test');
        $this->assertEquals(['test', 'test'], $this->query->getHighlight());
        $this->assertFalse($this->query->shouldFollowSpellcheck());
        $this->query->setFollowSpellcheck(true);
        $this->assertTrue($this->query->shouldFollowSpellcheck());
        $this->query->addFacetFilter('Test', 'test');
        $this->assertEquals(['Test' => ['test']], $this->query->getFacetFilter());
        $this->assertEquals(['Test' => ['test']], $this->query->getAndFacetFilter());
        $this->query->setFacetFilter(['Testing' => [1, 2]]);
        $this->assertEquals(['Testing' => [1, 2]], $this->query->getFacetFilter());
        $this->assertEquals(['Testing' => [1, 2]], $this->query->getAndFacetFilter());
        $this->query->setOrFacetFilter(['Testing' => [1, 2]]);
        $this->query->setAndFacetFilter(['Test' => [1, 2]]);
        $this->assertEquals(['Test' => [1, 2]], $this->query->getFacetFilter());
        $this->assertEquals(['Test' => [1, 2]], $this->query->getAndFacetFilter());
        $this->assertNotEquals(['Testing' => [1, 2]], $this->query->getFacetFilter());
        $this->assertEquals(['Testing' => [1, 2]], $this->query->getOrFacetFilter());
        $this->query->addAndFacetFilter('Test2', [3, 4]);
        $this->assertEquals($this->query->getAndFacetFilter(), $this->query->getFacetFilter());
        $expected = [
            'Test' => [1, 2],
            'Test2' => [3, 4]
        ];
        $this->assertEquals($expected, $this->query->getAndFacetFilter());
        $this->query->addOrFacetFilter('Test', [2, 3]);
        $this->assertNotEquals($this->query->getAndFacetFilter(), $this->query->getOrFacetFilter());
        $expected = [
            'Testing' => [1, 2],
            'Test' => [2, 3]
        ];
        $this->assertEquals($expected, $this->query->getOrFacetFilter());
    }

    protected function setUp()
    {
        $this->query = Injector::inst()->get(BaseQuery::class);
        parent::setUp();
        Injector::inst()->get(Page::class)->requireDefaultRecords();
        foreach (self::$extra_dataobjects as $className) {
            Config::modify()->merge($className, 'extensions', [DataObjectExtension::class]);
        }
    }
}
