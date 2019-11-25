<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class TestIndex extends BaseIndex
{
    protected $facetFields = [
        SiteTree::class => [
            'Title' => 'Parent',
            'Field' => 'SiteTree_ParentID',
        ],
    ];

    public function init(): void
    {
        $this->addClass(SiteTree::class);
        $this->addFulltextField('Title');
        $this->addFulltextField('Content');
        $this->addFilterField('Title');
        $this->addFilterField('Created');
        $this->addSortField('Created');
    }

    public function getIndexName(): string
    {
        return 'TestIndex';
    }
}
