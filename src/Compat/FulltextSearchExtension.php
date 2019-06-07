<?php


namespace Firesphere\SolrSearch\Compat;

use Firesphere\SolrSearch\Results\SearchResult;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\View\ArrayData;

/**
 * Class FulltextSearchExtension
 * Backward compatibility stubs for the Full text search module
 * @package Firesphere\SolrSearch\Extensions
 */
class FulltextSearchExtension extends Extension
{

    /**
     * Convert the SearchResult class to a Full text search compatible ArrayData
     * @param SearchResult|ArrayData $results
     */
    public function updateSearchResults(&$results): void
    {
        $request = Controller::curr()->getRequest();
        $data = [
            'Matches'               => $results->getPaginatedMatches($request),
            'Facets'                => $results->getFacets(),
            'Highlights'            => $results->getHighlight(),
            'Spellcheck'            => $results->getSpellcheck(),
            'Suggestion'            => $results->getCollatedSpellcheck(),
            'SuggestionNice'        => $this->getCollatedNice($results->getCollatedSpellcheck()),
            'SuggestionQueryString' => $results->getCollatedSpellcheck()
        ];
        // Override the results with an FTS compatible feature list
        $results = ArrayData::create($data);
    }

    /**
     * Create a spellcheck string that's not the literal collation with Solr query parts
     *
     * @param string $spellcheck
     * @return string mixed
     */
    protected function getCollatedNice($spellcheck): string
    {
        return str_replace(' +', ' ', $spellcheck);
    }
}
