<?php
/**
 * Created by PhpStorm.
 * User: simon
 * Date: 27-Mar-19
 * Time: 18:01
 */

namespace Firesphere\SolrSearch\Models;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

/**
 * Class \Firesphere\SolrSearch\Models\SearchField
 *
 * @property string $Field
 * @property string $Type
 * @property int $ParentID
 * @method SearchClass Parent()
 * @method DataList|SearchClass[] Children()
 */
class SearchField extends DataObject
{
    private static $table_name = 'SearchField';

    private static $db = [
        'Field' => 'Varchar(255)',
        'Type'  => 'Enum("Fulltext,Filter,Sort","Fulltext")'
    ];

    private static $has_one = [
        'Parent' => SearchClass::class
    ];

    private static $has_many = [
        'Children' => SearchClass::class
    ];
}
