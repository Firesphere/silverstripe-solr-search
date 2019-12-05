<?php


namespace tests\mocks;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\Dev\TestOnly;

class TestIndexFour extends BaseIndex implements TestOnly
{

    /**
     * @inheritDoc
     */
    public function getIndexName()
    {
        return 'index4';
    }
}
