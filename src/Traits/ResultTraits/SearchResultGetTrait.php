<?php


namespace Firesphere\SolrSearch\Traits;


use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use Solarium\Component\Result\Highlighting\Highlighting;

trait SearchResultGetTrait
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
     * @var string
     */
    protected $collatedSpellcheck;

    /**
     * @return ArrayData
     */
    public function getFacets(): ArrayData
    {
        return $this->facets;
    }

    /**
     * @return string
     */
    public function getCollatedSpellcheck()
    {
        return $this->collatedSpellcheck;
    }

    /**
     * @return Highlighting|null
     */
    public function getHighlight(): ?Highlighting
    {
        return $this->highlight;
    }

    /**
     * @return ArrayList
     */
    public function getSpellcheck(): ArrayList
    {
        return $this->spellcheck;
    }

    /**
     * @return int
     */
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }
}
