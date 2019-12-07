<?php


namespace Firesphere\SolrSearch\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class ClearErrorsTask extends BuildTask
{
    /**
     * @var string URLSegment
     */
    private static $segment = 'SolrClearErrorsTask';
    /**
     * @var string Title
     */
    protected $title = 'Clear out all errors from Solr in the database';
    /**
     * @var string Description
     */
    protected $description = 'Remove all errors in the database that are related to Solr indexing/configuring etc.';

    /**
     * @inheritDoc
     */
    public function run($request)
    {
        echo _t(__class__ . ".CLEARLOG",
            "Emptying logs for table SolrLog." . PHP_EOL . " WARNING: Any logs that are not inspected will be gone soon.");
        DB::query('TRUNCATE TABLE `SolrLog`');
    }
}
