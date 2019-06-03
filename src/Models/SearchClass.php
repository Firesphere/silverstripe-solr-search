<?php

namespace Firesphere\SolrSearch\Models;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

/**
 * Class SearchClass to be populated by default from config
 *
 * @package Firesphere\SolrSearch\Models
 * @property string $Name
 * @property int $SearchFieldID
 * @method SearchField SearchField()
 * @method DataList|SearchField[] SearchFields()
 */
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
