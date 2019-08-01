<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Helpers\Synonyms;
use SilverStripe\Dev\SapphireTest;

class SynonymsTest extends SapphireTest
{
    public function testGetSynonyms()
    {
        $synonyms = Synonyms::config()->get('ukus');

        $this->assertEquals($synonyms['synonyms'], Synonyms::getSynonyms());
        $this->assertEmpty(Synonyms::getSynonyms(false));
    }

    public function testGetSynonymsAsString()
    {
        $synonyms = Synonyms::config()->get('usuk');
        $synonyms = $synonyms['synonyms'];
        $rendered = Synonyms::getSynonymsAsString();

        $this->assertStringEndsWith("\n", $rendered);

        // Add 1 to the count, because the rendered string ends with a newline, thus adding one item to the array
        $this->assertCount(count($synonyms) + 1, explode("\n", $rendered));
    }
}
