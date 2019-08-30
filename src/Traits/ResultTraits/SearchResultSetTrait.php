<?php


namespace Firesphere\SolrSearch\Traits;

use Firesphere\SolrSearch\Results\SearchResult;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use Solarium\Component\Result\Highlighting\Highlighting;

/**
 * Trait SearchResultSetTrait
 * @package Firesphere\SolrSearch\Traits
 */
trait SearchResultSetTrait
{
    /**
     * @var int
     */
    protected $totalItems;
    /**
     * @var ArrayData
     */
    protected $facets;

    /**
     * @var Highlighting
     */
    protected $highlight;

    /**
     * @var ArrayList
     */
    protected $spellcheck;

    /**
     * @param $highlight
     * @return SearchResult
     */
    public function setHighlight($highlight): self
    {
        $this->highlight = $highlight;

        return $this;
    }

    /**
     * @param $check
     * @return SearchResult
     */
    public function setSpellcheck($check): self
    {
        $this->spellcheck = $check;

        return $this;
    }

    /**
     * @param $count
     * @return self
     */
    public function setTotalItems($count): self
    {
        $this->totalItems = $count;

        return $this;
    }
}
