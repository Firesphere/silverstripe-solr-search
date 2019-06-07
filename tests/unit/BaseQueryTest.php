<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Queries\BaseQuery;
use SilverStripe\CMS\Model\SiteTree;
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
        $this->query->addField('test', 'test');
        $this->assertEquals(['test' => 'test'], $this->query->getFields());
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
        $this->query->addFacetField(SiteTree::class, [
                'Field' => 'Name_of_Field',
                'Title' => 'TitleToUseForRetrieving'
            ]
        );
        $this->assertCount(1, $this->query->getFacetFields());
        $this->query->setFacetFields([
                SiteTree::class,
                [
                    'Field' => 'Name_of_Field',
                    'Title' => 'TitleToUseForRetrieving'
                ]
            ]
        );
        $this->assertCount(1, $this->query->getFacetFields());
        $this->query->setSpellcheck(false);
        $this->assertFalse($this->query->isSpellcheck());
        $this->query->addBoostedField('Field1', 2);
        $this->assertEquals(2, $this->query->getBoostedFields()['Field1']);
        $this->query->setBoostedFields(['Field' => 2]);
        $this->assertEquals(2, $this->query->getBoostedFields()['Field']);
    }
}
