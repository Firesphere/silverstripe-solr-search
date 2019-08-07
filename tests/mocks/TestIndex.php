<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class TestIndex extends BaseIndex implements TestOnly
{
    protected $facetFields = [
        SiteTree::class => [
            'Title' => 'Parent',
            'Field' => 'SiteTree.ParentID'
        ]
    ];

    public function init(): void
    {
        $this->addClass(SiteTree::class);
        $this->addFulltextField('Title');
        $this->addFulltextField('Content');
        $this->addFilterField('Title');
        $this->addFilterField('Created');
        $this->addFilterField('ParentID');
        $this->addSortField('Created');
        parent::init();
    }

    public function getIndexName(): string
    {
        return 'TestIndex';
    }
}
