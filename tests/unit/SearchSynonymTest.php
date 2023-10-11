<?php

namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Models\SearchSynonym;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;

class SearchSynonymTest extends SapphireTest
{
    public function testGetCMSFields()
    {
        $synonym = SearchSynonym::create();

        $fields = $synonym->getCMSFields();

        $this->assertInstanceOf(FieldList::class, $fields);
        $this->assertNotNull($fields->dataFieldByName('Keyword'));
        $this->assertNotNull($fields->dataFieldByName('Synonym'));
    }
}
