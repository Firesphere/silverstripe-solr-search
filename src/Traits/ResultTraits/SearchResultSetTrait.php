<?php


namespace Firesphere\SolrSearch\Traits;


use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use Solarium\Component\Result\Highlighting\Highlighting;

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
     * @return Highlighting|null
     */
    public function setHighlight(): ?Highlighting
    {
        return $this->highlight;
    }

    /**
     * @return ArrayList
     */
    public function setSpellcheck(): ArrayList
    {
        return $this->spellcheck;
    }

    /**
     * @return int
     */
    public function setTotalItems(): int
    {
        return $this->totalItems;
    }
}
