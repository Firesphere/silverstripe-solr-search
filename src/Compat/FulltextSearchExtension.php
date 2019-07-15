<?php


namespace Firesphere\SolrSearch\Compat;

use Firesphere\SolrSearch\Indexes\BaseIndex;
use Firesphere\SolrSearch\Queries\BaseQuery;
use Firesphere\SolrSearch\Results\SearchResult;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\View\ArrayData;

/**
 * Class FulltextSearchExtension
 * Backward compatibility stubs for the Full text search module
 *
 * @package Firesphere\SolrSearch\Extensions
 * @property FulltextSearchExtension $owner
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

    /**
     * @param BaseQuery $query
     * @param int $start deprecated in favour of $query, exists for backward compatibility with FTS
     * @param int $limit deprecated in favour of $query, exists for backward compatibility with FTS
     * @param array $params deprecated in favour of $query, exists for backward compatibility with FTS
     * @param bool $spellcheck deprecated in favour of #query, exists for backward compatibility with FTS
     * @return SearchResult|ArrayData|mixed
     * @deprecated This is used as an Fulltext Search compatibility method. Call doSearch instead with the correct Query
     */
    public function search($query, $start = 0, $limit = 10, $params = [], $spellcheck = null)
    {
        $query->getStart() === $start ?: $query->setStart($start);
        $query->getRows() === $limit ?: $query->setRows($limit);
        $query->hasSpellcheck() !== $spellcheck ?: $query->setSpellcheck($spellcheck);
        if (isset($params['fq']) && !count($query->getFields())) {
            $query->setFields($params['fq']);
        }

        /** @var BaseIndex $owner */
        $owner = $this->owner;

        return $owner->doSearch($query);
    }
}
