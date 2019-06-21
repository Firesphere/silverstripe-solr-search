<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Factories\DocumentFactory;
use Firesphere\SolrSearch\Helpers\SearchIntrospection;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\SapphireTest;
use Solarium\Core\Client\Client;
use Solarium\QueryType\Update\Query\Document\DocumentInterface;

class DocumentFactoryTest extends SapphireTest
{
    public function testConstruct()
    {
        $factory = new DocumentFactory();
        $this->assertInstanceOf(SearchIntrospection::class, $factory->getIntrospection());
    }

    public function testBuildItems()
    {
        $items = SiteTree::get();
        $factory = new DocumentFactory();
        $index = new TestIndex();
        $fields = $index->getFieldsForIndexing();
        $client = new Client([]);
        $update = $client->createUpdate();
        $count = 0;
        $docs = $factory->buildItems(
            SiteTree::class,
            $fields,
            $index,
            $update,
            0,
            $count,
            false
        );

        $this->assertTrue(is_array($docs));
        /** @var DocumentInterface $doc */
        foreach ($docs as $doc) {
            print_r(json_encode($doc->getFields(), JSON_PRETTY_PRINT));
        }
    }
}
