<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Jobs\SolrClearDirtyClassesJob;
use Firesphere\SolrSearch\Models\DirtyClass;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Page;
use SilverStripe\Dev\SapphireTest;

class SolrClearDirtyClassesJobTest extends SapphireTest
{
    public function testGetTitle()
    {
        $job = new SolrClearDirtyClassesJob();
        $this->assertEquals(
            'Clear out dirty objects in Solr',
            $job->getTitle()
        );
    }

    public function testProcess()
    {
        $job = new SolrClearDirtyClassesJob();
        $page = Page::create(['Title' => 'UpdatePageTest']);
        $id = $page->write();

        $dirtyClass = DirtyClass::create([
            'Type'  => SolrCoreService::UPDATE_TYPE,
            'Class' => Page::class,
            'IDs'   => json_encode([$id]),
        ])->write();

        $job->process();

        $this->assertNull(DirtyClass::get()->byID($dirtyClass));

        $dirtyClass = DirtyClass::create([
            'Type'  => SolrCoreService::DELETE_TYPE,
            'Class' => Page::class,
            'IDs'   => json_encode([$id]),
        ])->write();

        $job->process();

        $this->assertNull(DirtyClass::get()->byID($dirtyClass));

        $page->delete();
    }
}
