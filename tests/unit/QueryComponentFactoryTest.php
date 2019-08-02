<?php


namespace Firesphere\SolrSearch\Tests;

use CircleCITestIndex;
use Firesphere\SolrSearch\Factories\QueryComponentFactory;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Queries\BaseQuery;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Solarium\Core\Query\Helper;

class QueryComponentFactoryTest extends SapphireTest
{

    /**
     * @var QueryComponentFactory
     */
    protected $factory;

    public function testBuildQuery()
    {
        $index = new CircleCITestIndex();
        $query = new BaseQuery();
        $clientQuery = $index->getClient()->createSelect();
        $query->addTerm('Home');
        $query->addField('SiteTree_Title');
        $query->addFilter('SiteTree_Title', 'Home');
        $query->addExclude('SiteTree_Title', 'Contact');
        $query->addBoostedField('SiteTree.Content', [], 2);

        $this->factory->setQuery($query);
        $this->factory->setIndex($index);
        $this->factory->setClientQuery($clientQuery);
        $this->factory->setHelper($clientQuery->getHelper());
        $this->factory->setQueryArray(['Home']);

        $this->factory->buildQuery();

        $expected = ['Home', 'SiteTree_Content:Home^2.0'];
        $this->assertEquals($expected, $this->factory->getQueryArray());
        $this->assertEquals(['SiteTree_Title'], $this->factory->getClientQuery()->getFields());
        $this->assertCount(3, $this->factory->getClientQuery()->getFilterQueries());
        $this->assertInstanceOf(Helper::class, $this->factory->getHelper());
        $this->assertInstanceOf(BaseIndex::class, $this->factory->getIndex());
        $this->assertInstanceOf(BaseQuery::class, $this->factory->getQuery());
    }


    public function testEscapeTerms()
    {
        $term = '"test me" help';

        $helper = $this->factory->getIndex()->getClient()->createSelect()->getHelper();

        $escaped = $this->factory->escapeSearch($term, $helper);
        $this->assertEquals('"\"test me\"" help', $escaped);

        $term = 'help me';

        $this->assertEquals('help me', $this->factory->escapeSearch($term, $helper));
    }


    protected function setUp()
    {
        $this->factory = new QueryComponentFactory();
        $this->factory->setIndex(Injector::inst()->get(CircleCITestIndex::class));

        return parent::setUp();
    }
}
