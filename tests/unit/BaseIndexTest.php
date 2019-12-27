<?php


namespace Firesphere\SolrSearch\Tests;

use CircleCITestIndex;
use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Helpers\Synonyms;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Models\SearchSynonym;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Results\SearchResult;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Stores\FileConfigStore;
use Firesphere\SolrSearch\Tasks\SolrConfigureTask;
use Firesphere\SolrSearch\Tasks\SolrIndexTask;
use Page;
use Psr\Log\NullLogger;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\Security\DefaultAdminService;
use SilverStripe\View\ArrayData;
use Solarium\Component\Result\Highlighting\Highlighting;
use Solarium\Core\Client\Client;
use tests\mocks\TestIndexFour;

class BaseIndexTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures/DataResolver.yml';
    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        TestObject::class,
        TestPage::class,
        TestRelationObject::class,
    ];

    /**
     * @var array
     */
    protected static $required_extensions = [
        DataObject::class => [DataObjectExtension::class],
    ];

    /**
     * @var BaseIndex
     */
    protected $index;

    public function testConstruct()
    {
        ob_start();
        $this->assertInstanceOf(Client::class, $this->index->getClient());
        $this->assertCount(1, $this->index->getClasses());
        $this->assertCount(2, $this->index->getFulltextFields());
        $this->assertContains(SiteTree::class, $this->index->getClasses());
        ob_end_clean();
    }

    public function testInit()
    {
        $this->assertNull($this->index->init());
        $this->assertNotEmpty($this->index->getFulltextFields());
        $this->assertNotEmpty($this->index->getFieldsForIndexing());
        $expected = [
            'Title',
            'Content',
            'ParentID',
            'Created',
        ];

        $this->assertEquals($expected, array_values($this->index->getFieldsForIndexing()));

        $index = new TestIndexTwo();

        $this->assertCount(3, $index->getFulltextFields());
        $this->assertCount(1, $index->getFacetFields());
    }

    public function testAddAllFieldsTypes()
    {
        $this->index->addAllFulltextFields();

        $expected = [
            'Title',
            'Content',
            'ClassName',
            'URLSegment',
            'MenuTitle',
            'MetaDescription',
            'ExtraMeta',
            'ReportClass',
        ];

        $fulltextFields = $this->index->getFulltextFields();
        foreach ($expected as $field) {
            $this->assertContains($field, $fulltextFields);
        }

        $this->index->addAllDateFields();

        // Created is not supposed to be in here for unknown reasons
        $expected = [
            'LastEdited',
        ];

        $fulltextFields = $this->index->getFulltextFields();

        foreach ($expected as $field) {
            $this->assertContains($field, $fulltextFields);
        }
    }

    public function testFacetFields()
    {
        /** @var Page $parent */
        $parent = $this->objFromFixture(Page::class, 'homepage');
        $parent->publishRecursive();
        $id = $parent->ID;
        $page1 = Page::create(['Title' => 'Test 1', 'ParentID' => $id, 'ShowInSearch' => true]);
        $page1->write();
        $page1->publishRecursive();
        $page2 = Page::create(['Title' => 'Test 2', 'ParentID' => $id, 'ShowInSearch' => true]);
        $page2->write();
        $page2->publishRecursive();
        $task = new SolrIndexTask();
        $index = new TestIndex();
        $request = new HTTPRequest('GET', 'dev/tasks/SolrIndexTask', ['index' => TestIndex::class, 'unittest' => 1]);
        $task->run($request);
        $facets = $index->getFacetFields();
        $this->assertEquals([
            'BaseClass' => SiteTree::class,
            'Title'     => 'Parent',
            'Field'     => 'ParentID',
        ], $facets[SiteTree::class]);
        $query = new BaseQuery();
        $query->addTerm('Test');
        $clientQuery = $index->buildSolrQuery($query);
        $this->arrayHasKey('facet-Parent', $clientQuery->getFacetSet()->getFacets());
        $result = $index->doSearch($query);
        $this->assertInstanceOf(ArrayData::class, $result->getFacets());
        /** @var ArrayList $parents */
        $query->addFacetFilter('Parent', $id);
        $result = $index->buildSolrQuery($query);
        $filterQuery = $result->getFilterQuery('facet-Parent');
        $this->assertEquals('SiteTree_ParentID:' . $id, $filterQuery->getQuery());
        $query->setHighlight(['Test']);
        $result = $index->doSearch($query);
        $this->assertInstanceOf(Highlighting::class, $result->getHighlight());
    }

    public function testStoredFields()
    {
        $ftsFields = $this->index->getFulltextFields();
        $this->index->addStoredField('Test');
        $fields = $this->index->getStoredFields();

        $this->assertContains('Test', $fields);

        $this->index->setStoredFields(['Test', 'Test1']);

        $this->assertEquals(['Test', 'Test1'], $this->index->getStoredFields());

        $this->index->setFulltextFields($ftsFields);
    }

    public function testGetSynonyms()
    {
        $store = new FileConfigStore(['path' => '.solr']);
        $this->assertEquals(Synonyms::getSynonymsAsString(), $this->index->getSynonyms($store));

        $this->assertEmpty(trim($this->index->getSynonyms($store, false)));

        $synonym = SearchSynonym::create(['Keyword' => 'Test', 'Synonym' => 'testing,trying']);

        $synonym->write();

        $this->assertContains('Test,testing,trying', $this->index->getSynonyms($store));
    }

    public function testIndexName()
    {
        $this->assertEquals('TestIndex', $this->index->getIndexName());
    }

    public function testUploadConfig()
    {
        $config = [
            'mode' => 'file',
            'path' => '.solr',
        ];

        /** @var FileConfigStore $configStore */
        $configStore = Injector::inst()->create(FileConfigStore::class, $config);

        $this->index->uploadConfig($configStore);

        $this->assertFileExists(Director::baseFolder() . '/.solr/TestIndex/conf/schema.xml');
        $this->assertFileExists(Director::baseFolder() . '/.solr/TestIndex/conf/synonyms.txt');
        $this->assertFileExists(Director::baseFolder() . '/.solr/TestIndex/conf/stopwords.txt');

        $xml = file_get_contents(Director::baseFolder() . '/.solr/TestIndex/conf/schema.xml');
        $this->assertContains(
            '<field name="SiteTree_Title" type="string" indexed="true" stored="true" multiValued="false"/>',
            $xml
        );

        $original = $configStore->getConfig();
        $configStore->setConfig([]);
        $this->assertEquals([], $configStore->getConfig());
        // Unhappy path, the config is not updated
        $this->assertNotEquals($original, $configStore->getConfig());
    }

    public function testGetFieldsForIndexing()
    {
        $expected = [
            'Title',
            'Content',
            'ParentID',
            'Created',
        ];
        $this->assertEquals($expected, array_values($this->index->getFieldsForIndexing()));
    }

    public function testGetSetClient()
    {
        $client = $this->index->getClient();
        // set client to something stupid
        $this->index->setClient('test');
        $this->assertEquals('test', $this->index->getClient());
        $this->index->setClient($client);
        $this->assertInstanceOf(Client::class, $this->index->getClient());
    }

    public function testDoSearch()
    {
        $index = new CircleCITestIndex();

        $query = new BaseQuery();
        $query->addTerm('Home');

        $result = $index->doSearch($query);
        $this->assertInstanceOf(SearchResult::class, $result);
        $this->assertEquals(1, $result->getTotalItems());

        $admin = singleton(DefaultAdminService::class)->findOrCreateDefaultAdmin();
        $this->loginAs($admin);
        // Result should be the same for now
        $result2 = $index->doSearch($query);
        $this->assertEquals($result, $result2);

        $query->addClass(SiteTree::class);

        $result3 = $index->doSearch($query);
        $request = new NullHTTPRequest();
        $this->assertInstanceOf(PaginatedList::class, $result3->getPaginatedMatches($request));
        $this->assertEquals($result3->getTotalItems(), $result3->getPaginatedMatches($request)->getTotalItems());
        $this->assertInstanceOf(ArrayData::class, $result3->getFacets());
        $this->assertInstanceOf(ArrayList::class, $result3->getSpellcheck());
        $this->assertInstanceOf(Highlighting::class, $result3->getHighlight());

        $result3->setCustomisedMatches([]);
        $this->assertInstanceOf(ArrayList::class, $result3->getMatches());
        $this->assertCount(0, $result3->getMatches());

        $index = new CircleCITestIndex();
        $query = new BaseQuery();
        $query->addTerm('Home', ['SiteTree_Title'], 5);
        $result4 = $index->doSearch($query);

        $this->assertContains('SiteTree_Title:Home^5.0', $index->getQueryFactory()->getBoostTerms());
        $this->assertContains('Home', $index->getQueryTerms());
        $this->assertEquals(1, $result4->getTotalItems());

        $index = new CircleCITestIndex();
        $query = new BaseQuery();
        $query->addTerm('Home', ['SiteTree.Title'], 3);
        $result4 = $index->doSearch($query);

        $this->assertContains('SiteTree_Title:Home^3.0', $index->getQueryFactory()->getBoostTerms());
        $this->assertContains('Home', $index->getQueryTerms());
        $this->assertEquals(1, $result4->getTotalItems());

        $index = new CircleCITestIndex();
        $query = new BaseQuery();
        $query->addTerm('Home', [], 0, true);
        $index->doSearch($query);

        $this->assertContains('Home~', $index->getQueryTerms());

        $index = new CircleCITestIndex();
        $query = new BaseQuery();
        $query->addTerm('Hrme', [], 0);
        $query->setSpellcheck(true);
        $result = $index->doSearch($query);

        $this->assertInstanceOf(ArrayList::class, $result->getSpellcheck());
        $this->assertGreaterThan(0, $result->getSpellcheck()->count());
        $this->assertNotEmpty($result->getCollatedSpellcheck());
    }

    public function testDoRetry()
    {
        $index = new CircleCITestIndex();
        $query = new BaseQuery();
        $query->addTerm('Hrme', [], 0);
        $query->setSpellcheck(false);
        $index->doSearch($query);
        $queryArray = $index->getQueryTerms();

        $index = new CircleCITestIndex();
        $query = new BaseQuery();
        $query->addTerm('Hrme', [], 0);
        $query->setFollowSpellcheck(true);
        $query->setSpellcheck(true);
        $index->doSearch($query);
        $queryArray2 = $index->getQueryTerms();
        $this->assertNotEquals($queryArray, $queryArray2);
    }

    public function testSetFacets()
    {
        $this->index->addFacetField(
            Page::class,
            ['BaseClass' => Page::class, 'Title' => 'Title', 'Field' => 'Content']
        );

        $expected = [
            SiteTree::class => [
                'BaseClass' => SiteTree::class,
                'Title'     => 'Parent',
                'Field'     => 'ParentID',
            ],
            Page::class     => [
                'BaseClass' => Page::class,
                'Title'     => 'Title',
                'Field'     => 'Content',
            ],
        ];
        $this->assertEquals($expected, $this->index->getFacetFields());
    }

    public function testAddCopyField()
    {
        $this->index->addCopyField('mycopyfield', ['Content']);
        $expected = [
            '_text'       => ['*'],
            'mycopyfield' => ['Content'],
        ];
        $this->assertEquals($expected, $this->index->getCopyFields());
    }

    public function testAddFulltextField()
    {
        $this->index->addFulltextField('myfield', null, ['boost' => 2]);

        $this->assertArrayHasKey('myfield', $this->index->getBoostedFields());
        $this->assertEquals(2, $this->index->getBoostedFields()['myfield']);
    }

    public function testAddSortField()
    {
        $this->index->addSortField('TestField');

        $this->assertContains('TestField', $this->index->getFulltextFields());
        $this->assertContains('TestField', $this->index->getSortFields());
    }

    /**
     * @expectedException \LogicException
     */
    public function testInitException()
    {
        new TestIndexFour();
    }

    /**
     * @expectedException \LogicException
     */
    public function testInitExceptionNoKey()
    {
        Config::modify()->set(SolrCoreService::class, 'TestIndexFour', []);
        new TestIndexFour();
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testNotices()
    {
        $this->markTestSkipped('Deprecation does not properly reset, causing issues for other tests');
        Deprecation::set_enabled(true);
        $settings = Deprecation::dump_settings();
        Deprecation::notification_version(6.0);

        new TestIndexFour();

        Deprecation::restore_settings($settings);
        Deprecation::notification_version(1.0);
        Deprecation::set_enabled(false);
    }

    protected function setUp()
    {
        $task = new SolrConfigureTask();
        $task->setLogger(new NullLogger());
        $task->run(new NullHTTPRequest());

        $this->index = Injector::inst()->get(TestIndex::class, false);

        parent::setUp();
    }
}
