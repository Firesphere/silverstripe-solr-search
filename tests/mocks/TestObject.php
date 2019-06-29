<?php


namespace Firesphere\SolrSearch\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class TestObject extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar(255)'
    ];

    private static $has_many = [
        'TestPages' => TestPage::class,
        'TestRelation' => TestRelationObject::class
    ];
}
