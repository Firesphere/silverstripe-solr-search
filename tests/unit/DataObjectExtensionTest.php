<?php


namespace Firesphere\SolrSearch\Tests;


use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Group;

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
        $page->write();
        $page->ShowInSearch = true;
        $page->ViewerGroups()->add(Group::get()->filter(['Code' => 'administrators'])->first());
        $page->write();
        $this->assertEquals(['1-1'], $extension->getViewStatus());
    }
}