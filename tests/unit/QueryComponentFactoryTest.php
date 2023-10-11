<?php

namespace Firesphere\SolrSearch\Tests;

use CircleCITestIndex;
use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Factories\QueryComponentFactory;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Minimalcode\Search\Criteria;
use Page;
use ReflectionMethod;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Solarium\Core\Query\Helper;
use Solarium\QueryType\Select\Query\FilterQuery;
use Solarium\QueryType\Select\Query\Query;

class QueryComponentFactoryTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';

    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

    /**
     * @var QueryComponentFactory
     */
    protected $factory;

    protected function setUp()
    {
        parent::setUp();
        Injector::inst()->get(Page::class)->requireDefaultRecords();
        foreach (self::$extra_dataobjects as $className) {
            Config::modify()->merge($className, 'extensions', [DataObjectExtension::class]);
        }
        $this->factory = new QueryComponentFactory();
        $this->factory->setIndex(Injector::inst()->get(CircleCITestIndex::class));
    }

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
        $this->assertEquals(
            ['id', 'ObjectID', 'ClassName', 'SiteTree_Title'],
            $this->factory->getClientQuery()->getFields()
        );
        $this->assertCount(3, $this->factory->getClientQuery()->getFilterQueries());
        $this->assertInstanceOf(Helper::class, $this->factory->getHelper());
        $this->assertInstanceOf(BaseIndex::class, $this->factory->getIndex());
        $this->assertInstanceOf(BaseQuery::class, $this->factory->getQuery());
        $this->assertInternalType('array', $this->factory->getQueryArray());
        $this->assertInternalType('array', $this->factory->getBoostTerms());
    }

    public function testEscapeTerms()
    {
        $term = '"test me" help';

        $helper = $this->factory->getIndex()->getClient()->createSelect()->getHelper();
        $this->factory->setHelper($helper);
        $escaped = $this->factory->escapeSearch($term);
        $this->assertEquals('"test me" help', $escaped);

        $term = 'help me';
        $this->assertEquals('help me', $this->factory->escapeSearch($term));
    }

    public function testBuildCriteriaFilter()
    {
        $reflectionMethod = new ReflectionMethod(QueryComponentFactory::class, 'buildCriteriaFilter');
        $reflectionMethod->setAccessible(true);

        /** @var Criteria $criteria */
        $criteria = $reflectionMethod->invoke(new QueryComponentFactory(), 'TestField', 'TestValue');
        $this->assertInstanceOf(Criteria::class, $criteria);
        $this->assertEquals('TestField:TestValue', $criteria->getQuery());

        $criteria = $reflectionMethod->invoke(new QueryComponentFactory(), 'TestField', ['Arr1', 'Arr2']);
        $this->assertInstanceOf(Criteria::class, $criteria);
        $this->assertEquals('TestField:(Arr1 Arr2)', $criteria->getQuery());

        $criteriaValue = Criteria::where('TestField')->is('TestValue');
        $criteria = $reflectionMethod->invoke(new QueryComponentFactory(), 'TestField', $criteriaValue);
        $this->assertInstanceOf(Criteria::class, $criteria);
        $this->assertSame($criteriaValue, $criteria);
    }

    public function testFilterAndExcludeStrings()
    {
        $mockFilterQuery = $this->createMock(FilterQuery::class);
        $mockFilterQuery->expects($this->once())
            ->method('setQuery')
            ->with($this->equalTo('TestFilterField:TestFilterValue'));
        $mockExcludeQuery = $this->createMock(FilterQuery::class);
        $mockExcludeQuery->expects($this->once())
            ->method('setQuery')
            ->with($this->equalTo('-TestExcludeField:TestExcludeValue'));

        $mockClientQuery = new class () extends Query {
            public $mockFilterQuery;
            public $mockExcludeQuery;
            public function createFilterQuery($options = null): FilterQuery
            {
                if ($options === 'filter-TestFilterField') {
                    return $this->mockFilterQuery;
                } elseif ($options === 'exclude-TestExcludeField') {
                    return $this->mockExcludeQuery;
                }

                return parent::createFilterQuery($options);
            }
        };
        $mockClientQuery->mockFilterQuery = $mockFilterQuery;
        $mockClientQuery->mockExcludeQuery = $mockExcludeQuery;

        $baseQuery = new BaseQuery();
        $baseQuery->addFilter('TestFilterField', 'TestFilterValue');
        $baseQuery->addExclude('TestExcludeField', 'TestExcludeValue');

        $factory = new QueryComponentFactory();
        $factory->setIndex(Injector::inst()->get(CircleCITestIndex::class));
        $factory->setQuery($baseQuery);
        $factory->setClientQuery($mockClientQuery);
        $factory->buildQuery();
    }

    public function testFilterAndExcludeCriteria()
    {
        $mockFilterQuery = $this->createMock(FilterQuery::class);
        $mockFilterQuery->expects($this->once())
            ->method('setQuery')
            ->with($this->equalTo('TestFilterField:TestFilterValue'));
        $mockExcludeQuery = $this->createMock(FilterQuery::class);
        $mockExcludeQuery->expects($this->once())
            ->method('setQuery')
            ->with($this->equalTo('-TestExcludeField:TestExcludeValue'));

        $mockClientQuery = new class () extends Query {
            public $mockFilterQuery;
            public $mockExcludeQuery;
            public function createFilterQuery($options = null): FilterQuery
            {
                if ($options === 'filter-TestFilterField') {
                    return $this->mockFilterQuery;
                } elseif ($options === 'exclude-TestExcludeField') {
                    return $this->mockExcludeQuery;
                }

                return parent::createFilterQuery($options);
            }
        };
        $mockClientQuery->mockFilterQuery = $mockFilterQuery;
        $mockClientQuery->mockExcludeQuery = $mockExcludeQuery;

        $baseQuery = new BaseQuery();
        $baseQuery->addFilter('TestFilterField', Criteria::where('TestFilterField')->is('TestFilterValue'));
        $baseQuery->addExclude('TestExcludeField', Criteria::where('TestExcludeField')->is('TestExcludeValue'));

        $factory = new QueryComponentFactory();
        $factory->setIndex(Injector::inst()->get(CircleCITestIndex::class));
        $factory->setQuery($baseQuery);
        $factory->setClientQuery($mockClientQuery);
        $factory->buildQuery();
    }
}
