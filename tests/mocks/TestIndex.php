<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class TestIndex extends BaseIndex implements TestOnly
{
    public function init(): void
    {
        $this->addClass(SiteTree::class);
        $this->addFulltextField('Title');
        $this->addFulltextField('Content');
//        $this->addFulltextField('TestObject.Title');
//        $this->addFulltextField('TestObject.TestRelation.Title');
        $this->addFilterField('Title');
        $this->addFilterField('Created');
//        $this->addFacetField(TestObject::class, ['Field' => 'SiteTree_TestObjectID', 'Title' => 'TestObject']);
    }

    public function getIndexName(): string
    {
        return 'TestIndex';
    }
}
