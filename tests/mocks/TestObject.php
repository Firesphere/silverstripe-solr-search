<?php


namespace Firesphere\SolrSearch\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class TestObject extends DataObject implements TestOnly
{
    private static $table_name = 'TestObject';

    private static $db = [
        'Title'        => 'Varchar(255)',
        'ShowInSearch' => 'Boolean',
    ];

    private static $has_many = [
        'TestPages'    => TestPage::class,
        'TestRelation' => TestRelationObject::class,
    ];

    public function canView($member = null)
    {
        return true;
    }
}
