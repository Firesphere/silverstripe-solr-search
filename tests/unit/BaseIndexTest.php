<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Helpers\Synonyms;
use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Solarium\Core\Client\Client;

class BaseIndexTest extends SapphireTest
{
    /**
     * @var BaseIndex
     */
    protected $index;

    public function testConstruct()
    {
        $this->assertInstanceOf(Client::class, $this->index->getClient());
        $this->assertCount(1, $this->index->getClass());
        $this->assertCount(2, $this->index->getFulltextFields());
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

    protected function setUp()
    {
        $this->index = Injector::inst()->get(TestIndex::class);

        return parent::setUp();
    }
}
