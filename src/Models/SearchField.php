<?php
/**
 * Created by PhpStorm.
 * User: simon
 * Date: 27-Mar-19
 * Time: 18:01
 */

namespace Firesphere\SearchConfig\Models;

use SilverStripe\ORM\DataObject;

class SearchField extends DataObject
{
    private static $table_name = 'SearchField';

    private static $db = [
        'Field' => 'Varchar(255)',
        'Type' => 'Enum("Fulltext,Filter,Sort","Fulltext")'
    ];

    private static $has_one = [
        'Parent' => SearchClass::class
    ];

    private static $has_many = [
        'Children' => SearchClass::class
    ];
}
