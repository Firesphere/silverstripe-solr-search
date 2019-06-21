<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Helpers\Synonyms;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Stores\FileConfigStore;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\SapphireTest;
use Solarium\Core\Client\Client;
use SilverStripe\CMS\Model\SiteTree;

class BaseIndexTest extends SapphireTest
{
    /**
     * @var BaseIndex
     */
    protected $index;

    public function testConstruct()
    {
        $this->assertInstanceOf(Client::class, $this->index->getClient());
        $this->assertCount(1, $this->index->getClasses());
        $this->assertCount(2, $this->index->getFulltextFields());
        $this->assertTrue(in_array(SiteTree::class, $this->index->getClasses()));
    }

    public function testGetSynonyms()
    {
        $this->assertEquals(Synonyms::getSynonymsAsString(), $this->index->getSynonyms());

        $this->assertEmpty($this->index->getSynonyms(false));
    }

    public function testIndexName()
    {
        $this->assertEquals('TestIndex', $this->index->getIndexName());
    }

    public function testUploadConfig()
    {
        $config = [
            'mode' => 'file',
            'path' => Director::baseFolder() . '/.solr'
        ];

        $configStore = Injector::inst()->create(FileConfigStore::class, $config);

        $this->index->uploadConfig($configStore);

        $this->assertFileExists(Director::baseFolder() . '/.solr/TestIndex/conf/schema.xml');
        $this->assertFileExists(Director::baseFolder() . '/.solr/TestIndex/conf/synonyms.txt');
        $this->assertFileExists(Director::baseFolder() . '/.solr/TestIndex/conf/stopwords.txt');

        $xml = file_get_contents(Director::baseFolder() . '/.solr/TestIndex/conf/schema.xml');
        $this->assertContains('<field name=\'SiteTree_Title\' type=\'string\' indexed=\'true\' stored=\'true\' multiValued=\'false\'/>', $xml);
    }

    public function testEscapeTerms()
    {
        $term = '"test me" help';

        $helper = $this->index->getClient()->createSelect()->getHelper();

        $escaped = $this->index->escapeSearch($term, $helper);
        $this->assertEquals('"\"test me\"" help', $escaped);

        $term = 'help me';

        $this->assertEquals('help me', $this->index->escapeSearch($term, $helper));
    }

    public function testGetFieldsForIndexing()
    {
        $expected = [
            'Title',
            'Content',
            'Created'
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

    protected function setUp()
    {
        $this->index = Injector::inst()->get(TestIndex::class);

        return parent::setUp();
    }
}
