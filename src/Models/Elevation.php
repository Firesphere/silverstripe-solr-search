<?php

namespace Firesphere\SolrSearch\Models;

use SilverStripe\ORM\DataObject;

class Elevation extends DataObject
{
    private static $table_name = 'Elevation';

    private static $db = [
        'Keyword' => 'Varchar(255)',
    ];

    private static $many_many = [
        'Items' => ElevatedItem::class,
    ];

    private static $summary_fields = ['ID', 'Keyword'];
}
