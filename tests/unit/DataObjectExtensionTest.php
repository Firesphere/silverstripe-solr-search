<?php

namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Extensions\GridFieldExtension;
use Firesphere\SolrSearch\Models\DirtyClass;
use Firesphere\SolrSearch\Models\SolrLog;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Tasks\SolrConfigureTask;
use Page;
use Psr\Log\NullLogger;
use SilverStripe\Control\Controller;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\DefaultAdminService;

class DataObjectExtensionTest extends SapphireTest
{
    public function testGetViewStatus()
    {
        $page = new Page();
        $extension = new DataObjectExtension();
        $extension->setOwner($page);

        $this->assertEquals(['1-null'], $extension->getViewStatus());
        $page->ShowInSearch = false;
        $this->assertEmpty($extension->getViewStatus());

        $member = (new DefaultAdminService())->findOrCreateDefaultAdmin();
        $groups = $member->Groups();
        $group = $groups->first();
        $page->CanViewType = 'OnlyTheseUsers';
        $page->ShowInSearch = true;
        foreach ($groups as $group) {
            $page->ViewerGroups()->add($group);
        }
        $page->write();
        $extension->setOwner($page);
        DataObjectExtension::$canViewClasses = [];
        $this->assertContains('1-' . $group->Members()->first()->ID, $extension->getViewStatus());
        $page->delete();

        $item = new TestObject();
        $extension = new DataObjectExtension();
        $extension->setOwner($item);

        $this->assertEquals(['1-null'], $extension->getViewStatus());
        // TestObject has a ShowInSearch :)
        $this->assertArrayHasKey($item->ClassName, DataObjectExtension::$canViewClasses);
        $item = new TestRelationObject();
        $extension = new DataObjectExtension();
        $extension->setOwner($item);

        $this->assertEquals(['1-null'], $extension->getViewStatus());
        // TestObject has a ShowInSearch :)
        $this->assertArrayHasKey($item->ClassName, DataObjectExtension::$canViewClasses);

        $object = new CanViewObject();
        $extension = new DataObjectExtension();
        $extension->setOwner($object);

        $this->assertNotContains('1-null', $extension->getViewStatus());
        $this->assertContains('1-' . $member->ID, $extension->getViewStatus());
        $this->assertArrayHasKey($item->ClassName, DataObjectExtension::$canViewClasses);
        $this->assertEquals($extension->getViewStatus(), DataObjectExtension::$canViewClasses[CanViewObject::class]);
    }

    public function testOnAfterDelete()
    {
        $page = new Page(['Title' => 'Test']);
        $id = $page->write();
        $extension = new DataObjectExtension();
        $extension->setOwner($page);
        $service = new SolrCoreService();
        $service->setInDebugMode(false);
        $service->updateItems(ArrayList::create([$page]), SolrCoreService::DELETE_TYPE);

        $extension->onAfterDelete();
        /** @var DirtyClass $dirty */
        $dirty = DirtyClass::get()->filter(['Class' => Page::class, 'Type' => SolrCoreService::DELETE_TYPE])->first();
        $ids = json_decode($dirty->IDs, 1);
        $this->assertArrayNotHasKey($id, array_flip($ids));
        $page->delete();
    }

    public function testGetExtraClass()
    {
        $cleanDO = DataObject::create();

        $extension = new GridFieldExtension();

        $emptyClasses = [];
        $extension->updateNewRowClasses($emptyClasses, 1, '', $cleanDO);
        $this->assertNotContains('alert alert-warning', $emptyClasses);

        $logItem = SolrLog::create();
        $logItem->Level = 'WARN';
        $emptyClasses = [];
        $extension->updateNewRowClasses($emptyClasses, 1, '', $logItem);
        $this->assertContains('alert alert-warning', $emptyClasses);

        $logItem->Level = 'INFO';
        $emptyClasses = [];
        $extension->updateNewRowClasses($emptyClasses, 1, '', $logItem);
        $this->assertContains('alert alert-info', $emptyClasses);

        $logItem->Level = 'ERROR';
        $emptyClasses = [];
        $extension->updateNewRowClasses($emptyClasses, 1, '', $logItem);
        $this->assertContains('alert alert-danger', $emptyClasses);

        $logItem->Level = 'SOMETHING';
        $emptyClasses = [];
        $extension->updateNewRowClasses($emptyClasses, 1, '', $logItem);
        $this->assertContains('alert alert-info', $emptyClasses);
    }

    public function testOnAfterWrite()
    {
        $url = Controller::curr()->getRequest()->getURL();
        Controller::curr()->getRequest()->setUrl('dev/build');
        $this->assertNull((new DataObjectExtension())->onAfterWrite());
        Controller::curr()->getRequest()->setUrl($url);
    }

    protected function setUp()
    {
        $task = new SolrConfigureTask();
        $task->setLogger(new NullLogger());
        $task->run(new NullHTTPRequest());

        return parent::setUp();
    }
}
