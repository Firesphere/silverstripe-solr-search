<?php


namespace Firesphere\SolrSearch\Tasks;

use Exception;
use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Helpers\SolrLogger;
use Firesphere\SolrSearch\Models\DirtyClass;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Traits\LoggerTrait;
use GuzzleHttp\Exception\GuzzleException;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\ValidationException;

/**
 * Class ClearDirtyClasses
 * Clear out classes that were not succesfully updated or deleted in Solr.
 *
 * Any classes that failed to index properly or be removed properly need to be cleaned out regularly
 * This task takes care of doing this. It can be run directly via /dev/tasks, or via a queued job
 *
 * @package Firesphere\SolrSearch\Tasks
 */
class ClearDirtyClassesTask extends BuildTask
{
    use LoggerTrait;
    /**
     * @var string URLSegment
     */
    private static $segment = 'SolrClearDirtyClasses';
    /**
     * @var string Title
     */
    protected $title = 'Fix broken items in the Solr cores';
    /**
     * @var string Description
     */
    protected $description = 'Clear out classes that are marked as dirty on Solr.';

    /**
     * Clean up Dirty Classes in the index
     *
     * @param HTTPRequest $request
     * @return void
     * @throws GuzzleException
     * @throws \ReflectionException
     * @throws ValidationException
     */
    public function run($request)
    {
        /** @var DataList|DirtyClass $dirtyObjectList */
        $dirtyObjectList = DirtyClass::get();
        /** @var SolrCoreService $service */
        $service = new SolrCoreService();
        $solrLogger = new SolrLogger();
        foreach ($dirtyObjectList as $dirtyObject) {
            $dirtyClass = $dirtyObject->Class;
            $ids = json_decode($dirtyObject->IDs, true);
            try {
                $type = SolrCoreService::UPDATE_TYPE;
                $dirtyClasses = ArrayList::create();
                if ($dirtyObject->Type === SolrCoreService::UPDATE_TYPE) {
                    $dirtyClasses = $dirtyClass::get()->byIDs($ids);
                }
                if ($dirtyObject->Type === SolrCoreService::DELETE_TYPE) {
                    $dirtyClasses = $this->createDeleteList($ids, $dirtyClass);
                    $type = SolrCoreService::DELETE_TYPE;
                }
                $service->updateItems($dirtyClasses, $type);
                $dirtyObject->delete();
            } catch (Exception $exception) {
                $this->getLogger()->error($exception->getMessage());
                continue;
            }
        }
        $solrLogger->saveSolrLog('Index');
    }

    /**
     * Create an ArrayList of the dirty items to be deleted from Solr
     * Uses the given class name to generate stub objects
     *
     * @param array $items
     * @param string $dirtyClass
     * @return ArrayList
     */
    protected function createDeleteList($items, $dirtyClass): ArrayList
    {
        /** @var ArrayList $deletions */
        $deletions = ArrayList::create();
        foreach ($items as $item) {
            $dirtItem = $dirtyClass::create(['ID' => $item]);
            $deletions->push($dirtItem);
        }

        return $deletions;
    }
}
