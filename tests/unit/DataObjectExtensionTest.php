<?php

namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\SapphireTest;
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
        $page->CanViewType = 'OnlyTheseUsers';
        $page->write();
        (new DefaultAdminService())->findOrCreateDefaultAdmin();
        $group = Group::get()->filter(['Code' => ['ADMIN', 'administrators']])->first();
        $page->ViewerGroups()->add($group);
        $page->write();
        $this->assertEquals(['1-' . $group->Members()->first()->ID], $extension->getViewStatus());
        $page->delete();
    }
}
