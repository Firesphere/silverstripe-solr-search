<?php


namespace Firesphere\SolrSearch\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;

/**
 * Class \Firesphere\SolrSearch\Models\QueryRecording
 *
 * @property string $Query
 * @property int $Results
 * @property int $PagesVisited
 * @package Firesphere\SolrSearch\Models
 */
class QueryRecord extends DataObject
{
    private static $table_name = 'QueryRecording';

    private static $db = [
        'Query'        => 'Varchar(255)',
        'Results'      => 'Int',
        'PagesVisited' => 'Int',
    ];

    /**
     * @param $query
     * @param $count
     * @return QueryRecord
     * @throws ValidationException
     */
    public static function findOrCreate($query, $count): QueryRecord
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
