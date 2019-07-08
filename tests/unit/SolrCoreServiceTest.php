<?php


namespace Firesphere\SolrSearch\Tests;

use CircleCITestIndex;
use Firesphere\SolrSearch\Services\SolrCoreService;
use SilverStripe\Dev\SapphireTest;

class SolrCoreServiceTest extends SapphireTest
{
    public function testIndexes()
    {
        $service = new SolrCoreService();

        $expected = [
            CircleCITestIndex::class,
            TestIndex::class,
            TestIndexTwo::class,
        ];

        $this->assertEquals($expected, $service->getValidIndexes());
        $this->assertEquals([\CircleCITestIndex::class], $service->getValidIndexes(\CircleCITestIndex::class));
    }
}
