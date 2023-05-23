<?php

namespace Firesphere\SolrSearch\Models;

use SilverStripe\ORM\DataObject;

class ElevatedItem extends DataObject
{
    private static $table_name = 'ElevatedItem';

    private static $db = [
        'Rank' => 'Int',
        'Title' => 'Varchar(255)',
        'ObjectClass' => 'Varchar(255)',
        'ObjectID' => 'Int',
        'SolrID' => 'Varchar(255)',
        'Include' => 'Boolean(1)',
        'Exclude' => 'Boolean(0)',
    ];

    private static $belongs_many_many = [
        'Keywords' => Elevation::class,
    ];

    private static $summary_fields = ['Title', 'Rank', 'ObjectClass', 'SolrID', 'Include', 'Exclude'];
}
