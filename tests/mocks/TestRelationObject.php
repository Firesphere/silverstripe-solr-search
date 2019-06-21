<?php


namespace Firesphere\SolrSearch\Tests;


use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class TestRelationObject extends DataObject implements TestOnly
{

    private static $db = [
        'Title' => 'Varchar(255)'
    ];

    private static $has_one = [
        'Test' => TestObject::class
    ];
}