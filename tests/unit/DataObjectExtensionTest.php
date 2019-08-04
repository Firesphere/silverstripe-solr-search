<?php

namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Models\DirtyClass;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Tasks\SolrConfigureTask;
use Page;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\DefaultAdminService;
use SilverStripe\Security\Group;

/**
 * For unclear reasons, this is currently broken
 */
class DataObjectExtensionTest extends SapphireTest
{
    public function testGetViewStatus()
    {
        $page = new \Page();
        $extension = new DataObjectExtension();
        $extension->setOwner($page);

        $this->assertEquals(['1-null'], $extension->getViewStatus());
        $page->ShowInSearch = false;
        $this->assertEmpty($extension->getViewStatus());
        // @todo fix this assertion. It's breaking for unknown reasons
        $member = (new DefaultAdminService())->findOrCreateDefaultAdmin();
        $groups = $member->Groups();
        $page->CanViewType = 'OnlyTheseUsers';
        $page->ShowInSearch = true;
        foreach ($groups as $group) {
            $page->ViewerGroups()->add($group);
        }
        $page->write();
        $extension->setOwner($page);
        DataObjectExtension::$canViewClasses = [];
        $this->assertEquals(['1-' . $group->Members()->first()->ID], $extension->getViewStatus());
        $page->delete();

        $item = new TestObject();
        $extension->setOwner($item);

        $this->assertEquals(['1-null'], $extension->getViewStatus());
        $this->assertEquals(['1-null'], DataObjectExtension::$canViewClasses[$item->ClassName]);
    }

    public function testOnAfterDelete()
    {
        $page = new \Page(['Title' => 'Test']);
        $id = $page->write();
        $extension = new DataObjectExtension();
        $extension->setOwner($page);
        $service = new SolrCoreService();
        $service->setInDebugMode(false);
        $service->updateItems(ArrayList::create([$page]), SolrCoreService::CREATE_TYPE);

        $extension->onAfterDelete();
        $dirty = DirtyClass::get()->filter(['Class' => \Page::class, 'Type' => DataObjectExtension::DELETE])->first();
        $ids = json_decode($dirty->IDs);
        $this->assertArrayNotHasKey($id, array_flip($ids));
        $page->delete();
    }

    protected function setUp()
    {
        $task = new SolrConfigureTask();
        $task->run(new NullHTTPRequest());

        return parent::setUp();
    }
}
