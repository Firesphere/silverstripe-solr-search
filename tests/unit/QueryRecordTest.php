<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Models\QueryRecord;
use SilverStripe\Dev\SapphireTest;

class QueryRecordTest extends SapphireTest
{
    public function testFindOrCreate()
    {
        $this->assertEquals(1, QueryRecord::findOrCreate('Test', 10)->ID);
        $this->assertEquals(20, QueryRecord::findOrCreate('Test', 20)->Results);
    }
}
