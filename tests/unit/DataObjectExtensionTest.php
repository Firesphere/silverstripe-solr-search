<?php

namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use Firesphere\SolrSearch\Extensions\GridFieldExtension;
use Firesphere\SolrSearch\Models\DirtyClass;
use Firesphere\SolrSearch\Models\SolrLog;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Services\SolrCoreService;
use Firesphere\SolrSearch\Tasks\SolrConfigureTask;
use Page;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\Core\Injector\Injector;
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

        $this->assertEquals(['null'], $extension->getViewStatus());
        $page->ShowInSearch = false;
        $this->assertEquals(['false'], $extension->getViewStatus());

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
        DataObjectExtension::$cachedClasses = [];
        $this->assertContains($group->Code, $extension->getViewStatus());
        $page->CanViewType = 'LoggedInUsers';
        $extension->setOwner($page);
        $page->write();
        $this->assertEquals(['false', 'LoggedIn'], $extension->getViewStatus());
        $page->delete();

        $item = new TestObject();
        $extension = new DataObjectExtension();
        $extension->setOwner($item);

        $this->assertEquals(['null'], $extension->getViewStatus());

        $item = new TestRelationObject();
        $extension = new DataObjectExtension();
        $extension->setOwner($item);

        $this->assertEquals(['null'], $extension->getViewStatus());
        // TestObject has a ShowInSearch :)
        $this->assertArrayHasKey($item->ClassName, DataObjectExtension::$cachedClasses);

        $object = new CanViewObject();
        $extension = new DataObjectExtension();
        $extension->setOwner($object);

        $this->assertNotContains('false', $extension->getViewStatus());
        $this->assertContains('null', $extension->getViewStatus());
        $this->assertArrayHasKey($item->ClassName, DataObjectExtension::$cachedClasses);
        $this->assertEquals($extension->getViewStatus(), DataObjectExtension::$cachedClasses[CanViewObject::class]);
    }

    public function testOnAfterDelete()
    {
        $page = new Page(['Title' => 'Test']);
        $id = $page->write();
        $extension = new DataObjectExtension();
        $extension->setOwner($page);
        $service = new SolrCoreService();
        $service->setDebug(false);
        $service->updateItems(ArrayList::create([$page]), SolrCoreService::CREATE_TYPE);

        $extension->onAfterDelete();
        /** @var DirtyClass $dirty */
        $dirty = DirtyClass::get()->filter(['Class' => Page::class, 'Type' => SolrCoreService::DELETE_TYPE])->first();
        $ids = json_decode($dirty->IDs, 1);
        $this->assertArrayNotHasKey($id, array_flip($ids));
        $page->delete();
    }

    public function testGetExtraClass()
    {
        /** @var DataObject $cleanDO */
        $cleanDO = DataObject::create();

        $extension = new GridFieldExtension();

        $emptyClasses = [];
        $extension->updateNewRowClasses($emptyClasses, 1, '', $cleanDO);
        $this->assertNotContains('alert alert-warning', $emptyClasses);

        /** @var DataObject|SolrLog $logItem */
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
        $extension = new DataObjectExtension();
        $extension->setOwner(new DataObject());
        $this->assertNull($extension->onAfterWrite());
        $this->assertNull($extension->onAfterPublish());
        Controller::curr()->getRequest()->setUrl($url);
    }

    public function testDoNotShowInSearch()
    {
        $page = Page::create(['Title' => 'ShowInSearch Now']);
        $page->write();
        $page->publishRecursive();

        $query = new BaseQuery();
        $query->addTerm('ShowInSearch Now');

        $count = (new TestIndex())->doSearch($query);

        $counted = $count->getMatches()->count();

        $page->ShowInSearch = false;
        $page->write();
        $page->publishRecursive();

        $count = (new TestIndex())->doSearch($query);

        $counted2 = $count->getMatches()->count();

        $this->assertNotEquals($counted, $counted2);
        $page->ShowInSearch = true;
        $page->doReindex();

        $count = (new TestIndex())->doSearch($query);

        $counted2 = $count->getMatches()->count();

        $this->assertEquals($counted, $counted2);
    }

    protected function setUp()
    {
        /** @var CacheInterface $cache */
        $cache = Injector::inst()->get(CacheInterface::class . '.SolrCache');
        $cache->delete('ValidClasses');
        $task = new SolrConfigureTask();
        $task->setLogger(new NullLogger());
        $task->run(new NullHTTPRequest());

        return parent::setUp();
    }
}
