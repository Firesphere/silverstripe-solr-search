<?php


namespace Firesphere\SolrSearch\Tests;


use Firesphere\SolrSearch\Models\DirtyClass;
use SilverStripe\Dev\SapphireTest;

class DirtyClassTest extends SapphireTest
{

    public function testCan()
    {
        $dirtyClass = DirtyClass::create();

        $this->assertFalse($dirtyClass->canView(null, []));
        $this->assertFalse($dirtyClass->canEdit(null));
        $this->assertFalse($dirtyClass->canDelete(null));
    }

    public function testGetCMSFields()
    {
        $class = DirtyClass::create();
        $fields = $class->getCMSFields();

        $this->assertNotNull($fields->dataFieldByName('Class'));
        $this->assertNotNull($fields->dataFieldByName('IDs'));
    }
}
