<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Results\SearchResult;
use SilverStripe\View\ArrayData;
use Solarium\Component\Result\Highlighting\Highlighting;

/**
 * Trait SearchResultSetTrait
 *
 * @package Firesphere\SolrSearch\Traits
 */
trait SearchResultSetTrait
{
    /**
     * @var int Total items in result
     */
    protected $totalItems;
    /**
     * @var ArrayData Facets
     */
    protected $facets;

    /**
     * @var Highlighting Highlighted items
     */
    protected $highlight;

    /**
     * Set the highlighted items
     *
     * @param $highlight
     * @return SearchResult
     */
    public function setHighlight($highlight): self
    {
        $this->highlight = $highlight;

        return $this;
    }

    /**
     * Set the total amount of results
     *
     * @param $count
     * @return self
     */
    public function setTotalItems($count): self
    {
        $this->totalItems = $count;

        return $this;
    }
}
