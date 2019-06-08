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
    }

    public function getIndexName(): string
    {
        return 'TestIndex';
    }
}
