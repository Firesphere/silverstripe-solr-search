<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Indexes\BaseIndex;

class TestIndexTwo extends BaseIndex implements TestOnly
{

    public function getIndexName(): string
    {
        return 'TestIndexTwo';
    }
}
