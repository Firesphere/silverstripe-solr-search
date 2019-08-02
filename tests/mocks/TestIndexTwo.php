<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\Dev\TestOnly;

class TestIndexTwo extends BaseIndex implements TestOnly
{
    public function getIndexName(): string
    {
        return 'TestIndexTwo';
    }
}
