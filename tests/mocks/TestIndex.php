<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class TestIndex extends BaseIndex implements TestOnly
{
    protected $facetFields = [
        SiteTree::class => [
            'BaseClass' => SiteTree::class,
            'Title'     => 'Parent',
            'Field'     => 'ParentID',
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
        $this->addFacetField(SiteTree::class, [
            'BaseClass' => SiteTree::class,
            'Title'     => 'Parent',
            'Field'     => 'ParentID',
        ]);
    }

    public function getIndexName(): string
    {
        return 'TestIndex';
    }
}
