<?php


namespace Firesphere\SolrSearch\Tests;

use Page;
use SilverStripe\Dev\TestOnly;

class TestPage extends Page implements TestOnly
{
    private static $has_one = [
        'TestObject' => TestObject::class,
    ];

    private static $has_many = [
    ];

    public function getSalutation()
    {
        return sprintf('Dear %s', $this->Title);
    }
}
