<?php


namespace Firesphere\SolrSearch\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Class \Firesphere\SolrSearch\Models\DirtyClass
 * Keeping track of Dirty classes in Solr
 *
 * @property string $Dirty
 * @property string $Clean
 * @property string $Class
 * @property string $IDs
 */
class DirtyClass extends DataObject
{
    private static $table_name = 'DirtyClass';
    /**
     * @var array
     */
    private static $db = [
        'Dirty' => DBDatetime::class,
        'Clean' => DBDatetime::class,
        'Class' => 'Varchar(512)',
        'IDs'   => 'Varchar(255)',
    ];
}
