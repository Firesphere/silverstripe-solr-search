<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class TestIndexTwo extends BaseIndex // implements TestOnly
{
    public function init(): void
    {
        $this->addClass(SiteTree::class);
        $this->addFulltextField('Title');
        $this->addFulltextField('Content');
        $this->addFulltextField('TestObject.Title');
        $this->addFulltextField('TestObject.TestRelation.Title');
        $this->addFilterField('Title');
        $this->addFilterField('Created');
        $this->addFacetField(TestObject::class, ['Field' => 'SiteTree_TestObjectID', 'Title' => 'TestObject']);
        parent::init();
    }

    public function getIndexName(): string
    {
        return 'TestIndexTwo';
    }
}
