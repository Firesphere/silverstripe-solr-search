<?php

namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Extensions\DataObjectExtension;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\SapphireTest;
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
        $page->delete();
    }
}
