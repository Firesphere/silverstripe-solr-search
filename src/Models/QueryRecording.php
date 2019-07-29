<?php


namespace Firesphere\SolrSearch\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;

class QueryRecording extends DataObject
{
    private static $db = [
        'Query' => 'Varchar(255)',
        'Results' => 'Int',
        'PagesVisited' => 'Int',
    ];

    /**
     * @param $query
     * @param $count
     * @return QueryRecording
     * @throws ValidationException
     */
    public static function getOrCreate($query, $count): QueryRecording
    {
        $item = static::get()->filter(['Query' => trim($query)])->first();
        if (!$item || !$item->exists()) {
            $item = static::create([
                'Query' => $query,
            ]);
        }
        $item->Results = $count;

        $item->write();

        return $item;
    }
}
