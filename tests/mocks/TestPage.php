<?php

namespace Firesphere\SolrSearch\Tests;

use Page;
use SilverStripe\Dev\TestOnly;

/**
 * Class TestPage
 * @package Firesphere\Solr\Search
 */
class TestPage extends Page implements TestOnly
{
    /**
     * @var array
     */
    private static $has_one = [
        'TestObject' => TestObject::class,
    ];

    /**
     * @var array
     */
    private static $has_many = [
    ];

    private static $many_many = [
        'RelationObject' => TestRelationObject::class,
    ];

    /**
     * @return string
     */
    public function getSalutation()
    {
        return sprintf('Dear %s', $this->Title);
    }
}
