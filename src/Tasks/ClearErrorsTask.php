<?php


namespace Firesphere\SolrSearch\Tasks;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Class ClearErrorsTask
 *
 * Clear out errors from the database to declutter the CMS.
 *
 * @package Firesphere\SolrSearch\Tasks
 */
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
     * Run the truncate of the SolrLog table
     * @inheritDoc
     */
    public function run($request)
    {
        Injector::inst()->get(LoggerInterface::class)->warn(_t(
            __class__ . ".CLEARLOG",
            "Emptying logs for table SolrLog." . PHP_EOL . "WARNING: Any logs that are not inspected will be gone soon."
        ));
        DB::query('TRUNCATE TABLE `SolrLog`');
    }
}
