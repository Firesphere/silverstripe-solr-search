<?php


namespace Firesphere\SolrSearch\Tests;


use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Services\SchemaService;
use \TestIndex;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Solarium\Core\Client\Client;

class BaseIndexTest extends SapphireTest
{

    public function testConstruct()
    {
        /** @var BaseIndex $index */
        $index = Injector::inst()->get(TestIndex::class);
        $this->assertInstanceOf(Client::class, $index->getClient());
    }
}