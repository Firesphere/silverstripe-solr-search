<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\Dev\TestOnly;

class TestIndex3 extends BaseIndex implements TestOnly
{
    public function init()
    {
        return;
    }

    public function getIndexName()
    {
        return 'TestIndex-3';
    }
}
