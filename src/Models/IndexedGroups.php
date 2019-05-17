<?php

namespace Firesphere\SearchConfig\Models;

use SilverStripe\ORM\DataObject;

class IndexedGroups extends DataObject
{
    private static $table_name = 'IndexedGroups';

    private static $db = [
        'MaxGroup'  => 'Int',
        'LastIndex' => 'Datetime'
    ];
}
