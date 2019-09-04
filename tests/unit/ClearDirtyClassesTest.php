<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Models\DirtyClass;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Tasks\ClearDirtyClassesTask;
use Page;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Dev\SapphireTest;

class ClearDirtyClassesTest extends SapphireTest
{
    public function testRun()
    {
        $request = new NullHTTPRequest();
        $task = new ClearDirtyClassesTask();
        $page = Page::create(['Title' => 'UpdatePageTest']);
        $id = $page->write();

        $dirtyClass = DirtyClass::create([
            'Type'  => SolrCoreService::UPDATE_TYPE,
            'Class' => Page::class,
            'IDs'   => json_encode([$id]),
        ])->write();

        $task->run($request);

        $this->assertNull(DirtyClass::get()->byID($dirtyClass));

        $dirtyClass = DirtyClass::create([
            'Type'  => SolrCoreService::DELETE_TYPE,
            'Class' => Page::class,
            'IDs'   => json_encode([$id]),
        ])->write();

        $task->run($request);

        $this->assertNull(DirtyClass::get()->byID($dirtyClass));

        $page->delete();
    }
}
