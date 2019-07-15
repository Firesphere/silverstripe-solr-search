<?php


namespace Firesphere\SolrSearch\Models;

use SilverStripe\ORM\DataObject;

/**
 * Class \Firesphere\SolrSearch\Models\DirtyClass
 * Keeping track of Dirty classes in Solr
 *
 * @property string $Type
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
        'Type'  => 'Varchar(6)',
        'Class' => 'Varchar(512)',
        'IDs'   => 'Varchar(255)',
    ];
}
