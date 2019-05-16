<?php

namespace Firesphere\SearchConfig\Models;

use SilverStripe\ORM\DataObject;

class SearchClass extends DataObject
{
    private static $table_name = 'SearchClass';

    private static $db = [
        'Name' => 'Varchar(255)'
    ];

    private static $has_one = [
        'SearchField' => SearchField::class
    ];

    private static $has_many = [
        'SearchFields' => SearchField::class
    ];
}
